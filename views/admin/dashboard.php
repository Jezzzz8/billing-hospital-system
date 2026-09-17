<?php


?>

<div class="mb-8">
    <h2 class="text-xl font-bold text-slate-900">Welcome back, <?= htmlspecialchars($currentUser['first_name']) ?> </h2>
    <p class="mt-1 text-sm text-slate-500">Here's what's happening across the hospital today.</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $stats = [
        ['label' => 'Total Patients',    'value' => '5', 'color' => 'bg-blue-50 text-blue-700'],
        ['label' => 'Active Admissions', 'value' => '3', 'color' => 'bg-emerald-50 text-emerald-700'],
        ['label' => 'Available Rooms',   'value' => '2', 'color' => 'bg-amber-50 text-amber-700'],
        ['label' => 'Pending Bills',     'value' => '2', 'color' => 'bg-rose-50 text-rose-700'],
    ];
    foreach ($stats as $s): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide"><?= $s['label'] ?></p>
            <p class="mt-2 text-3xl font-bold text-slate-900"><?= $s['value'] ?></p>
   
        </div>
    <?php endforeach; ?>
</div>