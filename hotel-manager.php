<?php
// The canonical listing already provides persisted rooms, rates, availability and reservations.
require_once __DIR__ . '/includes/auth_session.php';
require_role('admin');
redirect('/listing.php');
