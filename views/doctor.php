<?php


require_once __DIR__ . '/../controllers/DoctorPortalController.php';
require_once __DIR__ . '/../controllers/DoctorHelper.php';

$doctorId = DoctorHelper::getCurrentDoctorId($pdo);

if (!$doctorId) {
    echo '<div class="bg-amber-50 border border-amber-200 rounded-lg p-6 text-amber-800">';
    echo '<p class="font-semibold">Doctor profile not found.</p>';
    echo '<p class="text-sm mt-1">Your account is not linked to a doctor record. Contact an administrator.</p>';
    echo '</div>';
    return;
}

$controller = new DoctorPortalController($pdo, $doctorId);
$stats = $controller->getDashboardStats();
$patients = $controller->getAssignedPatients();
$readyForDischarge = $controller->getReadyForDischarge();


?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Dashboard</h1>
    <p class="mt-1 text-sm text-slate-500">Your assigned patients and pending tasks.</p>
</div>


<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Assigned Admissions</p>
        <p class="mt-2 text-2xl font-bold text-slate-900"><?= $stats['assigned_admissions'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Today's Consultations</p>
        <p class="mt-2 text-2xl font-bold text-blue-600"><?= $stats['today_consultations'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Pending Requests</p>
        <p class="mt-2 text-2xl font-bold text-amber-600"><?= $stats['pending_requests'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Ready for Discharge</p>
        <p class="mt-2 text-2xl font-bold text-emerald-600"><?= $stats['ready_for_discharge'] ?></p>
    </div>
</div>


<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Assigned Patients</h3>
            <p class="text-xs text-slate-500 mt-0.5">Admissions assigned to you</p>
        </div>
        <a href="<?= BASE_URL ?>/index.php?page=doctor-patients" class="text-sm font-medium text-blue-600 hover:text-blue-700">View all →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="text-left px-6 py-3 font-medium">Patient</th>
                    <th class="text-left px-6 py-3 font-medium">Room</th>
                    <th class="text-left px-6 py-3 font-medium">Chief Complaint</th>
                    <th class="text-left px-6 py-3 font-medium">Admitted</th>
                    <th class="text-right px-6 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-400">No patients assigned to you.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach (array_slice($patients, 0, 10) as $p): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3">
                                <p class="font-medium text-slate-900"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></p>
                                <p class="text-xs text-slate-500">#<?= (int)$p['admission_id'] ?> · <?= htmlspecialchars($p['gender_name']) ?></p>
                            </td>
                            <td class="px-6 py-3">
                                <?php if (!empty($p['room_number'])): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                                          style="background-color: <?= htmlspecialchars($p['admission_status_color'] ?? '#6b7280') ?>1A;
                                                 color: <?= htmlspecialchars($p['admission_status_color'] ?? '#6b7280') ?>;
                                                 border-color: <?= htmlspecialchars($p['admission_status_color'] ?? '#6b7280') ?>40;">
                                        <?= htmlspecialchars($p['room_number']) ?>
                                    </span>
                                    <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($p['room_type_name'] ?? '') ?></p>
                                <?php else: ?>
                                    <span class="text-xs text-amber-600">No room</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-slate-600 max-w-md">
                                <p class="truncate"><?= htmlspecialchars($p['chief_complaint'] ?? '—') ?></p>
                            </td>
                            <td class="px-6 py-3 text-slate-600">
                                <?= htmlspecialchars(date('M j, Y', strtotime($p['admission_datetime']))) ?>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <a href="<?= BASE_URL ?>/index.php?page=doctor-diagnosis-record&admission_id=<?= (int)$p['admission_id'] ?>"
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    Open
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>