<?php


$users = $pdo->query(
    'SELECT u.user_id, u.username, u.first_name, u.last_name, u.email,
            u.contact_number, u.role_id, u.is_active, r.role_name
     FROM `user` u
     INNER JOIN `role` r ON r.role_id = u.role_id
     ORDER BY u.user_id'
)->fetchAll();
$roles = $pdo->query('SELECT role_id, role_name FROM `role` ORDER BY role_id')->fetchAll();
?>

<div class="mb-8 flex items-center justify-between">
    <div>
        <h2 class="text-xl font-bold text-slate-900">Users</h2>
        <p class="mt-1 text-sm text-slate-500">All registered staff accounts.</p>
    </div>
    <button type="button" id="openCreateBtn"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
        + New User
    </button>
</div>

<div id="alert" class="hidden mb-5 rounded-lg px-4 py-3 text-sm border"></div>


<div class="bg-white rounded-xl border border-slate-200 p-4 mb-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

        <div class="lg:col-span-2">
            <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="filterSearch"
                       placeholder="Search name, username, email…"
                       class="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm
                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Role</label>
            <select id="filterRole"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= htmlspecialchars($r['role_name']) ?>">
                        <?= htmlspecialchars(ucfirst($r['role_name'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 select-none cursor-pointer">
                <input type="checkbox" id="showArchived"
                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                Show archived
            </label>
        </div>

    </div>

    <div id="filterSummary" class="hidden mt-3 flex items-center justify-between text-xs">
        <span class="text-slate-500">
            Showing <strong id="filteredCount">0</strong> of <strong id="totalCount">0</strong> users
        </span>
        <button type="button" id="filterClear"
                class="text-blue-600 hover:text-blue-700 font-medium">Clear filters</button>
    </div>
</div>


<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="text-left px-6 py-3 font-medium">Name</th>
                <th class="text-left px-6 py-3 font-medium">Username</th>
                <th class="text-left px-6 py-3 font-medium">Email</th>
                <th class="text-left px-6 py-3 font-medium">Role</th>
                <th class="text-left px-6 py-3 font-medium">Status</th>
                <th class="text-right px-6 py-3 font-medium">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($users as $u): ?>
                <tr class="hover:bg-slate-50 user-row"
                    data-search="<?= htmlspecialchars(strtolower($u['first_name'] . ' ' . $u['last_name'] . ' ' . $u['username'] . ' ' . $u['email'])) ?>"
                    data-role="<?= htmlspecialchars($u['role_name']) ?>"
                    data-status="<?= (int)$u['is_active'] ?>">
                    <td class="px-6 py-3 font-medium text-slate-900">
                        <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                    </td>
                    <td class="px-6 py-3 text-slate-600"><?= htmlspecialchars($u['username']) ?></td>
                    <td class="px-6 py-3 text-slate-600"><?= htmlspecialchars($u['email']) ?></td>
                    <td class="px-6 py-3">
                        <span class="inline-block rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 capitalize">
                            <?= htmlspecialchars($u['role_name']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-3">
                        <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium
                            <?= $u['is_active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                            <?= $u['is_active'] ? 'Active' : 'Archived' ?>
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right whitespace-nowrap">

                        <?php if ((int)$u['role_id'] === 2): ?>
                            <span class="text-xs text-slate-400 italic">Managed on Doctors page</span>
                        <?php else: ?>

                            <button type="button"
                                    class="edit-btn inline-flex items-center rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    data-user='<?= htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8") ?>'>
                                Edit
                            </button>

                            <button type="button"
                                    class="pw-btn inline-flex items-center rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 ml-1"
                                    data-user-id="<?= (int)$u['user_id'] ?>"
                                    data-user-name="<?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>">
                                Password
                            </button>

                            <?php if ((int)$u['is_active'] === 1): ?>
                                <button type="button"
                                        class="deactivate-btn inline-flex items-center rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100 ml-1"
                                        data-user-id="<?= (int)$u['user_id'] ?>"
                                        data-user-name="<?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>">
                                    Deactivate
                                </button>
                            <?php else: ?>
                                <button type="button"
                                        class="reactivate-btn inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100 ml-1"
                                        data-user-id="<?= (int)$u['user_id'] ?>"
                                        data-user-name="<?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>">
                                    Reactivate
                                </button>
                            <?php endif; ?>

                        <?php endif; ?>

                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div id="emptyState" class="hidden text-center py-12 text-slate-400">
        <p class="text-sm">No users match your filters.</p>
    </div>
</div>


<div id="userModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" data-close-modal></div>

    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
            <h3 id="userModalTitle" class="text-lg font-semibold text-slate-900">New User</h3>
            <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="userForm" method="POST" novalidate class="p-6">
            <input type="hidden" id="user_id" name="user_id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">First Name</label>
                    <input type="text" id="first_name" required
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-red-600 hidden" data-error-for="first_name"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Last Name</label>
                    <input type="text" id="last_name" required
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-red-600 hidden" data-error-for="last_name"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Username</label>
                    <input type="text" id="username" required
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-red-600 hidden" data-error-for="username"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                    <input type="email" id="email" required
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-red-600 hidden" data-error-for="email"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Contact <span class="text-slate-400">(optional)</span>
                    </label>
                    <input type="text" id="contact_number"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Role</label>
                    <select id="role_id" required
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— Select a role —</option>
                        <?php foreach ($roles as $r): ?>
                            <?php if ((int)$r['role_id'] === 2) continue; ?>
                            <option value="<?= (int)$r['role_id'] ?>">
                                <?= htmlspecialchars(ucfirst($r['role_name'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-red-600 hidden" data-error-for="role_id"></p>
                </div>

                <div id="passwordFieldWrapper" class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <input type="password" id="password" minlength="6"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="At least 6 characters">
                    <p class="mt-1 text-xs text-red-600 hidden" data-error-for="password"></p>
                </div>

            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" data-close-modal
                        class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" id="userSubmitBtn"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    <span id="userSubmitLabel">Create User</span>
                </button>
            </div>
        </form>
    </div>
</div>


<div id="pwModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" data-close-pw></div>

    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-900">Change Password</h3>
            <button type="button" data-close-pw class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="pwForm" method="POST" novalidate class="p-6 space-y-4">
            <p class="text-sm text-slate-500">
                New password for <strong id="pwUserName" class="text-slate-900"></strong>.
            </p>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">New Password</label>
                <input type="password" id="pw_password" required minlength="6"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-xs text-red-600 hidden" data-error-for="password"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirm Password</label>
                <input type="password" id="pw_password_confirm" required minlength="6"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-xs text-red-600 hidden" data-error-for="password_confirm"></p>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" data-close-pw
                        class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" id="pwSubmitBtn"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    <span id="pwSubmitLabel">Update Password</span>
                </button>
            </div>
        </form>
    </div>
</div>


<div id="confirmModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" data-close-confirm></div>

    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-start gap-4">
            <div class="shrink-0 w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Archive user?</h3>
                <p class="mt-1 text-sm text-slate-500">
                    <strong id="confirmUserName" class="text-slate-900"></strong> will no longer be able to log in.
                    The account will be archived, not deleted. You can reactivate it later.
                </p>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <button type="button" data-close-confirm
                    class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Cancel
            </button>
            <button type="button" id="confirmDeactivateBtn"
                    class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-60">
                <span id="confirmDeactivateLabel">Yes, archive</span>
            </button>
        </div>
    </div>
</div>