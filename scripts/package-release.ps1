$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
$out=Join-Path $root 'delivery'
New-Item -ItemType Directory -Path $out -Force | Out-Null
$zip=Join-Path $out ('CRM-client-release-'+(Get-Date -Format 'yyyyMMdd-HHmmss')+'.zip')
$files=@(Get-ChildItem -LiteralPath $root -File -Filter '*.php' | Where-Object {$_.Name -notmatch '^(\.env|seed_data|test_)'})
foreach($folder in @('includes','assets')){$files+=Get-ChildItem -LiteralPath (Join-Path $root $folder) -File -Recurse}
foreach($path in @('.htaccess','.user.ini','.env.example.php','database/schema.sql','scripts/install.php','scripts/migrate_bookings.php','docs/CLIENT-HANDOVER.md','docs/booking-upgrade.md')){$files+=Get-Item -LiteralPath (Join-Path $root $path)}
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive=[IO.Compression.ZipFile]::Open($zip,[IO.Compression.ZipArchiveMode]::Create)
try {foreach($file in $files){$relative=$file.FullName.Substring($root.Length+1).Replace('\','/');[IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive,$file.FullName,$relative,[IO.Compression.CompressionLevel]::Optimal)|Out-Null}}finally{$archive.Dispose()}
$verify=[IO.Compression.ZipFile]::OpenRead($zip)
try {
 $names=@($verify.Entries.FullName)
 if($names -contains '.env.php' -or ($names -match '^(tests/|\.git/|seed_data|database/all.sql)')){throw 'Unsafe release contents'}
 if($names.Count -ne $files.Count){throw 'Missing release files'}
 "Verified $($names.Count) release files; credentials, test data and destructive SQL excluded."
}finally{$verify.Dispose()}
$hash=Get-FileHash -LiteralPath $zip -Algorithm SHA256
[IO.File]::WriteAllText($zip+'.sha256',$hash.Hash+'  '+[IO.Path]::GetFileName($zip))
$zip
