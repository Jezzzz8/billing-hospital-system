<?php

$rooms = $pdo->query(
    'SELECT r.room_id, r.room_number, r.floor_level, r.building,
            rt.room_type_name, rt.rate_per_day,
            rs.status_name, rs.color_code
     FROM `room` r
     INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
     INNER JOIN `room_status` rs ON rs.status_id = r.status_id
     ORDER BY r.room_id'
)->fetchAll();
?>

<div class="mb-8">
    <h2 class="text-xl font-bold text-slate-900">Rooms</h2>
    <p class="mt-1 text-sm text-slate-500">All hospital rooms and their current status.</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($rooms as $r): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="flex items-center justify-between">
                <p class="text-lg font-bold text-slate-900">Room <?= htmlspecialchars($r['room_number']) ?></p>
                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                      style="background: <?= htmlspecialchars($r['color_code']) ?>20; color: <?= htmlspecialchars($r['color_code']) ?>">
                    <?= htmlspecialchars($r['status_name']) ?>
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500"><?= htmlspecialchars($r['room_type_name']) ?></p>
            <p class="mt-3 text-sm text-slate-600">
                Floor <?= (int)$r['floor_level'] ?> · <?= htmlspecialchars($r['building']) ?>
            </p>
            <p class="mt-1 text-sm font-semibold text-slate-900">
                ₱<?= number_format((float)$r['rate_per_day'], 2) ?>/day
            </p>
        </div>
    <?php endforeach; ?>
</div>