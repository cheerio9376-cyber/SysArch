<?php
session_start();
if (!isset($_SESSION['student_id'])) { header("Location: index.php"); exit(); }
require 'config/db.php';

$sid = $_SESSION['student_id'];
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id=?");
$stmt->bind_param("s", $sid);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$firstname   = $student['firstname'];
$lastname    = $student['lastname'];
$course_level= $student['course_level'];
$course      = $student['course'];
$profile_pic = $student['profile_pic'];

// Announcements
$ann_res       = $conn->query("SELECT title,content,created_at FROM announcements ORDER BY created_at DESC");
$announcements = $ann_res->fetch_all(MYSQLI_ASSOC);
$new_count     = 0;
foreach ($announcements as $a) {
    if (strtotime($a['created_at']) > ($_SESSION['ann_last_seen'] ?? 0)) $new_count++;
}

$active_page = 'rules';
include 'includes/layout.php';
?>

<div class="page-header">
    <h2><i class="bi bi-file-earmark-text me-2"></i>Rules & Regulations</h2>
    <p>Please read and follow these guidelines to maintain a productive, safe lab environment for everyone.</p>
</div>

<!-- ── Tabs ─────────────────────────────────────────────── -->
<ul class="nav rules-tabs mb-4" id="rulesTabs">
    <li class="nav-item">
        <a class="rules-tab active" href="#" onclick="showTab('general',this)">
            <i class="bi bi-list-ul me-2"></i>General Rules
        </a>
    </li>
    <li class="nav-item">
        <a class="rules-tab" href="#" onclick="showTab('discipline',this)">
            <i class="bi bi-shield-exclamation me-2"></i>Disciplinary Policies
        </a>
    </li>
    <li class="nav-item">
        <a class="rules-tab" href="#" onclick="showTab('sitin',this)">
            <i class="bi bi-pc-display me-2"></i>Sit-In Guidelines
        </a>
    </li>
    <li class="nav-item">
        <a class="rules-tab" href="#" onclick="showTab('reminders',this)">
            <i class="bi bi-lightbulb me-2"></i>Quick Reminders
        </a>
    </li>
</ul>

<!-- ── General Rules ─────────────────────────────────────── -->
<div class="rules-panel" id="tab-general">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ccs-card">
                <div class="ccs-card-title"><i class="bi bi-list-ul"></i> General Laboratory Rules</div>
                <?php
                $general_rules = [
                    ['Valid student ID required',
                     'Students must present a valid school ID before using any computer lab. Sit-ins without proper identification will not be processed by the lab in-charge.'],
                    ['Session limit enforcement',
                     'Each student is allowed a maximum of 30 sit-in sessions per semester. Sessions cannot be carried over to the next semester and do not accumulate.'],
                    ['Log in and out properly',
                     'Always log your sit-in session through the CCS Sit-In Monitoring System. Unlogged sessions will not be counted and may result in the loss of reward points.'],
                    ['No food or drinks inside the lab',
                     'Eating and drinking near computer equipment is strictly prohibited. This protects hardware from damage and ensures the lab remains clean for all users.'],
                    ['Proper use of equipment',
                     'Only use assigned equipment for academic purposes. Gaming, extended social media use, and non-academic browsing are not permitted during sit-in hours.'],
                    ['Keep noise levels low',
                     'The lab is a shared academic space. Students must maintain a quiet, respectful environment. Online calls and meetings must use headphones.'],
                    ['Respect others\' property',
                     'Do not use or tamper with another student\'s files, peripherals, or personal belongings. Always ask before borrowing anything.'],
                    ['Leave the station clean',
                     'Return the computer station to its original state before leaving. Push in the chair, return peripherals, and discard any trash in designated bins.'],
                ];
                foreach ($general_rules as $i => [$title, $desc]):
                ?>
                <div class="rule-row">
                    <div class="rule-num"><?= $i + 1 ?></div>
                    <div class="rule-body">
                        <div class="rule-title"><?= $title ?></div>
                        <div class="rule-desc"><?= $desc ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="ccs-card rules-highlight purple mb-3">
                <i class="bi bi-calendar-check fs-2 mb-2 d-block"></i>
                <h5 class="fw-bold">Semester Limit</h5>
                <div class="display-5 fw-bold"><?= SEM_LIMIT ?></div>
                <p class="mb-0">maximum sit-in sessions per semester</p>
            </div>
            <div class="ccs-card rules-highlight gold">
                <i class="bi bi-clock-history fs-2 mb-2 d-block"></i>
                <h5 class="fw-bold">Lab Hours</h5>
                <p class="mb-1"><strong>Mon – Fri:</strong> 7:30 AM – 9:00 PM</p>
                <p class="mb-1"><strong>Saturday:</strong> 8:00 AM – 5:00 PM</p>
                <p class="mb-0"><strong>Sunday:</strong> Closed</p>
            </div>
        </div>
    </div>
</div>

<!-- ── Disciplinary Policies ──────────────────────────────── -->
<div class="rules-panel d-none" id="tab-discipline">
    <div class="ccs-card mb-4">
        <div class="ccs-card-title"><i class="bi bi-shield-exclamation text-danger"></i> Violations and Consequences</div>
        <div class="alert alert-warning mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Notice:</strong> Violations may result in suspension or permanent revocation of computer lab privileges, depending on severity.
        </div>
        <?php
        $policies = [
            ['warning','System misuse or manipulation',
             'Any attempt to manipulate, exploit, or abuse the CCS Sit-In Monitoring System — including logging sessions for others or falsifying sit-in times — will result in immediate suspension of lab privileges and referral to the Student Discipline Committee.',
             'Immediate suspension + SDC referral'],
            ['danger','Unauthorized access',
             'Using another student\'s credentials to log in, accessing restricted system areas, or bypassing authentication mechanisms is a serious violation subject to disciplinary action and possible legal consequences.',
             'Permanent lab ban + SDC + possible legal action'],
            ['warning','Vandalism and equipment damage',
             'Students found intentionally damaging computer equipment, furniture, or any lab property will be held financially liable for repair or replacement costs and may face academic sanctions.',
             'Financial liability + academic sanctions'],
            ['secondary','Unauthorized software installation',
             'Installing, downloading, or running unauthorized software — including games, cracking tools, or personal applications — without the permission of the lab in-charge is prohibited.',
             'Session termination + warning on record'],
            ['secondary','Excessive noise or disruption',
             'Repeatedly disrupting other students through loud conversations, music, or any other behavior after being warned by the lab in-charge will result in removal from the lab for that session.',
             'Removal from session'],
        ];
        foreach ($policies as $i => [$severity, $title, $desc, $consequence]):
            $icon = match($severity) { 'danger'=>'bi-x-octagon-fill text-danger', 'warning'=>'bi-exclamation-triangle-fill text-warning', default=>'bi-info-circle-fill text-secondary' };
        ?>
        <div class="policy-row">
            <div class="policy-icon"><i class="bi <?= $icon ?>"></i></div>
            <div class="policy-body">
                <div class="policy-title"><?= $title ?></div>
                <div class="policy-desc"><?= $desc ?></div>
                <div class="policy-consequence">
                    <i class="bi bi-arrow-right-circle me-1"></i>
                    <strong>Consequence:</strong> <?= $consequence ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Sit-In Guidelines ──────────────────────────────────── -->
<div class="rules-panel d-none" id="tab-sitin">
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="ccs-card">
                <div class="ccs-card-title"><i class="bi bi-play-circle"></i> Before Your Session</div>
                <?php
                $before = [
                    'Check the session counter in your dashboard — ensure you still have available sessions.',
                    'Bring your valid school ID. No ID means no sit-in, no exceptions.',
                    'If you plan to use a specific lab, make a reservation at least 24 hours in advance.',
                    'Prepare your materials (USB, printed references, etc.) before arriving to save time.',
                    'Arrive on time. Reserved slots are forfeited 15 minutes after the booked start time.',
                ];
                foreach ($before as $i => $item): ?>
                <div class="guide-item">
                    <span class="guide-num"><?= $i + 1 ?></span>
                    <span class="guide-text"><?= $item ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="ccs-card">
                <div class="ccs-card-title"><i class="bi bi-stop-circle"></i> During & After Your Session</div>
                <?php
                $during = [
                    'Log your session immediately after sitting down using the Sit-In Monitoring System.',
                    'Use only your assigned computer. Do not switch stations without permission.',
                    'Focus on academic work. Personal entertainment is not permitted during sit-in hours.',
                    'Alert the lab in-charge immediately if you notice any equipment malfunction.',
                    'Log out of the system before leaving — do not just close the browser.',
                    'Leaving without ending your session will count toward your session limit regardless of duration.',
                    'Submit feedback after your session to earn +10 reward points!',
                ];
                foreach ($during as $i => $item): ?>
                <div class="guide-item">
                    <span class="guide-num"><?= $i + 1 ?></span>
                    <span class="guide-text"><?= $item ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Quick Reminders ────────────────────────────────────── -->
<div class="rules-panel d-none" id="tab-reminders">
    <div class="row g-3">
        <?php
        $reminders = [
            ['bi-wifi','Internet Access','The lab provides internet access for academic use only. Streaming services and excessive downloads may affect connectivity for all users.','#2980b9'],
            ['bi-usb-drive','Personal Devices','Personal storage devices must be scanned for viruses before use. The lab is not responsible for data loss due to malware from personal devices.','#8e44ad'],
            ['bi-people','Group Work','Group work sessions are allowed but must be conducted quietly. Only the registered student\'s account may be used for logging the session.','#27ae60'],
            ['bi-power','Shutting Down','Always shut down or log off the computer properly when leaving. Never force-power-off a machine unless instructed by the lab in-charge.','#e74c3c'],
            ['bi-bag','Personal Belongings','The CCS lab is not responsible for lost or stolen items. Keep valuables secure and do not leave belongings unattended.','#d4a017'],
            ['bi-phone','Mobile Phones','Keep phones on silent mode while inside the lab. Phone calls should be taken outside the laboratory.','#5a3d82'],
            ['bi-thermometer-sun','Lab Temperature','Do not tamper with air conditioning units or electric fans. Report any temperature concerns to the lab in-charge.','#16a085'],
            ['bi-chat-dots','Reporting Issues','Report any problems (broken equipment, suspicious activity, etc.) directly to the lab in-charge or through the feedback system.','#e67e22'],
        ];
        foreach ($reminders as [$icon, $title, $desc, $color]): ?>
        <div class="col-md-6">
            <div class="reminder-card">
                <div class="reminder-icon" style="background:<?= $color ?>18;color:<?= $color ?>">
                    <i class="bi <?= $icon ?>"></i>
                </div>
                <div>
                    <div class="reminder-title"><?= $title ?></div>
                    <div class="reminder-desc"><?= $desc ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Acknowledgement Banner ─────────────────────────────── -->
<div class="ccs-card mt-4" style="background:var(--purple);color:#fff;text-align:center;border:none;">
    <i class="bi bi-check-circle-fill fs-2 mb-2 d-block" style="color:var(--gold)"></i>
    <h5 class="fw-bold mb-2">By using the CCS Computer Lab, you agree to follow all rules and regulations above.</h5>
    <p class="mb-0 small" style="opacity:.8">Violations are taken seriously and may affect your academic standing. When in doubt, ask the lab in-charge.</p>
</div>

<style>
/* Tabs */
.rules-tabs { display:flex;gap:8px;flex-wrap:wrap;border:none;padding:0; }
.rules-tab { display:flex;align-items:center;padding:9px 16px;border-radius:10px;font-size:0.875rem;font-weight:500;text-decoration:none;color:#555;border:1px solid rgba(90,61,130,.12);background:#fff;transition:all .15s; }
.rules-tab:hover { background:var(--purple-soft);color:var(--purple);text-decoration:none; }
.rules-tab.active { background:var(--purple);color:#fff;border-color:var(--purple); }

/* Rule rows */
.rule-row { display:flex;gap:14px;padding:14px 0;border-bottom:1px solid rgba(90,61,130,.08); }
.rule-row:last-child { border-bottom:none; }
.rule-num { min-width:30px;height:30px;border-radius:50%;background:var(--purple);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;flex-shrink:0;margin-top:2px; }
.rule-title { font-size:0.9rem;font-weight:600;color:#333;margin-bottom:4px; }
.rule-desc  { font-size:0.83rem;color:#555;line-height:1.6; }

/* Policy rows */
.policy-row { display:flex;gap:16px;padding:16px 0;border-bottom:1px solid rgba(90,61,130,.08); }
.policy-row:last-child { border-bottom:none; }
.policy-icon { font-size:1.4rem;flex-shrink:0;width:28px;text-align:center;margin-top:2px; }
.policy-title { font-size:0.9rem;font-weight:600;color:#333;margin-bottom:5px; }
.policy-desc  { font-size:0.83rem;color:#555;line-height:1.6;margin-bottom:6px; }
.policy-consequence { font-size:0.8rem;color:#e74c3c;background:#fdf2f2;padding:4px 10px;border-radius:8px;display:inline-block; }

/* Highlights */
.rules-highlight { text-align:center; }
.rules-highlight.purple { background:var(--purple);color:#fff;border:none; }
.rules-highlight.gold   { background:var(--gold);color:#fff;border:none; }

/* Guide items */
.guide-item { display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid rgba(90,61,130,.07); }
.guide-item:last-child { border-bottom:none; }
.guide-num  { min-width:24px;height:24px;border-radius:50%;background:var(--purple-soft);color:var(--purple);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0;margin-top:2px; }
.guide-text { font-size:0.85rem;color:#444;line-height:1.6; }

/* Reminder cards */
.reminder-card { display:flex;gap:14px;align-items:flex-start;padding:16px;background:#fff;border-radius:14px;border:1px solid rgba(90,61,130,.08); }
.reminder-icon { width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0; }
.reminder-title { font-size:0.9rem;font-weight:600;color:#333;margin-bottom:4px; }
.reminder-desc  { font-size:0.8rem;color:#555;line-height:1.5; }
</style>

<script>
function showTab(id, el) {
    event.preventDefault();
    document.querySelectorAll('.rules-panel').forEach(p => p.classList.add('d-none'));
    document.getElementById('tab-' + id).classList.remove('d-none');
    document.querySelectorAll('.rules-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
}
</script>

<?php include 'includes/layout_footer.php'; ?>