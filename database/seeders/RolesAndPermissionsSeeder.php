<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permissions ─────────────────────────────────────────
        $permissions = [
            // Jobs
            'jobs.view', 'jobs.create', 'jobs.edit', 'jobs.delete', 'jobs.finalize',
            // Calibration
            'calibrations.enter_data', 'calibrations.calculate',
            'calibrations.sign', 'calibrations.authorize', 'calibrations.release',
            // Certificates
            'certificates.view', 'certificates.download', 'certificates.send',
            // Instruments
            'instruments.view', 'instruments.create', 'instruments.edit',
            // Templates
            'templates.view', 'templates.manage',
            // Users
            'users.manage',
            // Reports
            'reports.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ── Roles ────────────────────────────────────────────────

        // Admin — full access
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // Lab Manager — everything except user management
        $labManager = Role::firstOrCreate(['name' => 'lab_manager']);
        $labManager->syncPermissions([
            'jobs.view','jobs.create','jobs.edit','jobs.finalize',
            'calibrations.enter_data','calibrations.calculate',
            'calibrations.authorize','calibrations.release',
            'certificates.view','certificates.download','certificates.send',
            'instruments.view','instruments.create','instruments.edit',
            'templates.view','templates.manage',
            'reports.view',
        ]);

        // Service Engineer — can authorize, not manage templates or users
        $engineer = Role::firstOrCreate(['name' => 'service_engineer']);
        $engineer->syncPermissions([
            'jobs.view','jobs.create','jobs.edit','jobs.finalize',
            'calibrations.enter_data','calibrations.calculate',
            'calibrations.authorize',
            'certificates.view','certificates.download',
            'instruments.view',
            'reports.view',
        ]);

        // Metrologist — data entry, calculation, and signing own work
        $metro = Role::firstOrCreate(['name' => 'metrologist']);
        $metro->syncPermissions([
            'jobs.view',
            'calibrations.enter_data','calibrations.calculate','calibrations.sign',
            'certificates.view',
            'instruments.view',
        ]);

        // Junior Metrologist — data entry only, cannot sign or calculate
        $junior = Role::firstOrCreate(['name' => 'junior_metrologist']);
        $junior->syncPermissions([
            'jobs.view',
            'calibrations.enter_data',
            'certificates.view',
            'instruments.view',
        ]);

        // Customer — portal only (separate guard in practice)
        Role::firstOrCreate(['name' => 'customer']);

        // ── Default admin user ───────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'admin@mcs.lk'],
            [
                'name'     => 'System Admin',
                'password' => \Hash::make('password'),
            ]
        );
        $user->assignRole('admin');

        // ── Sample metrologist ───────────────────────────────────
        $metro_user = User::firstOrCreate(
            ['email' => 'metrologist@mcs.lk'],
            [
                'name'     => 'Y H Athuraliya',
                'password' => \Hash::make('password'),
            ]
        );
        $metro_user->assignRole('metrologist');

        $this->command->info('✓ Roles and permissions seeded');
        $this->command->info('  Admin login:       admin@mcs.lk / password');
        $this->command->info('  Metrologist login: metrologist@mcs.lk / password');
    }
}
