<?php
/**
 * contact.php — Example "Contact" page using the common layout
 */
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

$activePage      = '';
$pageTitle       = 'Contact & Support';
$pageDescription = 'Contact or support page for Uttarakhand Ventures CRM.';

require_once __DIR__ . '/includes/left-sidebar.php';
require_once __DIR__ . '/includes/header.php';
?>

    <main class="main-content">

      <div class="grid grid-2">

        <!-- Contact Form -->
        <div class="panel">
          <div class="panel-title">
            <i class="bi bi-envelope" style="color:#4f46e5;"></i>
            Send a Message
          </div>
          <form>
            <div class="form-group">
              <label class="form-label">Your Name</label>
              <input type="text" class="form-control" placeholder="Enter your name">
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" class="form-control" placeholder="you@example.com">
            </div>
            <div class="form-group">
              <label class="form-label">Subject</label>
              <input type="text" class="form-control" placeholder="Issue or request summary">
            </div>
            <div class="form-group">
              <label class="form-label">Message</label>
              <textarea class="form-control" rows="5" placeholder="Describe your query..."></textarea>
            </div>
            <button type="submit" class="btn btn-brand">
              <i class="bi bi-send"></i> Send Message
            </button>
          </form>
        </div>

        <!-- Support Info -->
        <div class="panel">
          <div class="panel-title">
            <i class="bi bi-headset" style="color:#0d9488;"></i>
            Support Information
          </div>
          <div style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:38px;height:38px;border-radius:10px;background:#eef2ff;display:flex;align-items:center;justify-content:center;color:#4f46e5;font-size:16px;flex-shrink:0;">
                <i class="bi bi-telephone"></i>
              </div>
              <div>
                <div style="font-weight:700;font-size:.84rem;">Phone Support</div>
                <div style="font-size:.78rem;color:#64748b;margin-top:2px;">Mon–Sat, 9:00 AM – 6:00 PM</div>
                <div style="font-size:.84rem;font-weight:600;margin-top:4px;">+91 98765 43210</div>
              </div>
            </div>
            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:38px;height:38px;border-radius:10px;background:#e0f7f5;display:flex;align-items:center;justify-content:center;color:#0d9488;font-size:16px;flex-shrink:0;">
                <i class="bi bi-envelope"></i>
              </div>
              <div>
                <div style="font-weight:700;font-size:.84rem;">Email</div>
                <div style="font-size:.78rem;color:#64748b;margin-top:2px;">Response within 24 hours</div>
                <div style="font-size:.84rem;font-weight:600;margin-top:4px;">support@uttarakhandventures.in</div>
              </div>
            </div>
            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:38px;height:38px;border-radius:10px;background:#fff8e5;display:flex;align-items:center;justify-content:center;color:#f59e0b;font-size:16px;flex-shrink:0;">
                <i class="bi bi-geo-alt"></i>
              </div>
              <div>
                <div style="font-weight:700;font-size:.84rem;">Office Address</div>
                <div style="font-size:.78rem;color:#64748b;margin-top:4px;line-height:1.7;">
                  Uttarakhand Ventures Pvt. Ltd.<br>
                  MG Road, Dehradun,<br>
                  Uttarakhand – 248001
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

    </main>

<?php
require_once __DIR__ . '/includes/right-sidebar.php';
require_once __DIR__ . '/includes/footer.php';
?>
