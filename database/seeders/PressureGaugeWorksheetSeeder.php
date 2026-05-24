<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorksheetTemplate;
use App\Models\WorksheetField;
use App\Models\MasterInstrument;
use App\Models\CmcEntry;
use App\Models\User;

class PressureGaugeWorksheetSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Ensure a user exists for created_by ───────────────────
        $user = User::first();
        if (! $user) {
            $user = User::create([
                'name' => 'Seeder Admin',
                'email' => 'seed@local',
                'password' => bcrypt('password'),
            ]);
        }

        // ── 2. Worksheet template ────────────────────────────────────
        $template = WorksheetTemplate::updateOrCreate(
            ['code' => 'MCS-CAL-WS-01AN'],
            [
                'name'               => 'Pressure gauge analysis worksheet',
                'instrument_type'    => 'pressure_gauge',
                'test_method_ref'    => 'MCS/TM/01',
                'unit'               => 'bar',
                'max_test_points'    => 13,
                'cycles'             => 2,
                'has_before_after'   => true,
                'has_head_correction'=> true,
                'version'            => '1.0',
                'created_by'         => $user->id,
            ]
        );

        // ── 2. Formula nodes (mirrors Analysis sheet columns) ────────
        $nodes = [
            [
                'key'            => 'u_std_cal',
                'label'          => 'Standard calibration uncertainty',
                'symbol'         => 'δuSTD,CAL',
                'uncertainty_type'=> 'B',
                'formula'        => '{mpe}',
                'depends_on'     => ['mpe'],
                'divisor_formula'=> 'sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_std_drift',
                'label'          => 'Standard drift uncertainty',
                'symbol'         => 'δuSTD,DRIFT',
                'uncertainty_type'=> 'B',
                'formula'        => '{drift}',
                'depends_on'     => ['drift'],
                'divisor_formula'=> 'sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_std_res',
                'label'          => 'Reference resolution uncertainty',
                'symbol'         => 'δuSTD,RES',
                'uncertainty_type'=> 'B',
                'formula'        => '{resolution_ref}',
                'depends_on'     => ['resolution_ref'],
                'divisor_formula'=> '2*sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_hys',
                'label'          => 'Hysteresis uncertainty',
                'symbol'         => 'δuHys',
                'uncertainty_type'=> 'A',
                'formula'        => '{hysteresis}',
                'depends_on'     => ['hysteresis'],
                'divisor_formula'=> '2*sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_rep',
                'label'          => 'Repeatability uncertainty',
                'symbol'         => 'δuREP',
                'uncertainty_type'=> 'A',
                'formula'        => '{repeatability}',
                'depends_on'     => ['repeatability'],
                'divisor_formula'=> '2*sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_zero',
                'label'          => 'Zero error uncertainty',
                'symbol'         => 'δuZero',
                'uncertainty_type'=> 'A',
                'formula'        => '{zero_error}',
                'depends_on'     => ['zero_error'],
                'divisor_formula'=> '2*sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_std_scat',
                'label'          => 'Reference scatter uncertainty',
                'symbol'         => 'δuSTD,Scat',
                'uncertainty_type'=> 'A',
                'formula'        => '0',
                'depends_on'     => [],
                'divisor_formula'=> '2*sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_uuc_scat',
                'label'          => 'UUC scatter uncertainty',
                'symbol'         => 'δuUUC,Scat',
                'uncertainty_type'=> 'A',
                'formula'        => '0',
                'depends_on'     => [],
                'divisor_formula'=> '2*sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_uuc_res',
                'label'          => 'UUC resolution uncertainty',
                'symbol'         => 'δuUUC,RES',
                'uncertainty_type'=> 'B',
                'formula'        => '{resolution_uuc}',
                'depends_on'     => ['resolution_uuc'],
                'divisor_formula'=> '2*sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_hc',
                'label'          => 'Head correction uncertainty',
                'symbol'         => 'δuHC',
                'uncertainty_type'=> 'A',
                'formula'        => '{hc_bar}',
                'depends_on'     => ['hc_bar'],
                'divisor_formula'=> 'sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_res_cor',
                'label'          => 'Correction residual uncertainty',
                'symbol'         => 'δuResidual,Cor',
                'uncertainty_type'=> 'A',
                'formula'        => '{poly_residual_cor}',
                'depends_on'     => ['poly_residual_cor'],
                'divisor_formula'=> 'sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
            [
                'key'            => 'u_res_un',
                'label'          => 'Uncertainty residual',
                'symbol'         => 'δuResidual,Un',
                'uncertainty_type'=> 'A',
                'formula'        => '{poly_residual_un}',
                'depends_on'     => ['poly_residual_un'],
                'divisor_formula'=> 'sqrt(3)',
                'sensitivity_coeff' => 1,
            ],
        ];

        $template->formulaNodes()->delete();
        foreach ($nodes as $i => $n) {
            $template->formulaNodes()->create(array_merge($n, [
                'is_output'            => false,
                'scope_per_test_point' => true,
                'sort_order'           => $i,
            ]));
        }

        // ── 3. Worksheet fields (what the metrologist types in) ─────
        // Keys match what CalibrationEngine::buildTestPointContext() reads.
        // max_test_points = 13, cycles = 2 → engine reads up1/dn1/up2/dn2 per cycle.
        $template->fields()->delete();

        $sort = 0;

        // ── Setup fields (entered once per calibration) ──────────────
        $setupFields = [
            ['key' => 'resolution_uuc',  'label' => 'UUC Resolution',    'type' => 'number', 'unit' => 'bar', 'decimal_places' => 4, 'group' => 'UUC Setup'],
            ['key' => 'pressure_medium', 'label' => 'Pressure Medium',   'type' => 'select', 'options' => ['pneumatic','oil','water'], 'group' => 'UUC Setup'],
            ['key' => 'zero_error_c1',   'label' => 'Zero Error Cycle 1', 'type' => 'number', 'unit' => 'bar', 'decimal_places' => 4, 'group' => 'Zero Readings'],
            ['key' => 'zero_error_c2',   'label' => 'Zero Error Cycle 2', 'type' => 'number', 'unit' => 'bar', 'decimal_places' => 4, 'group' => 'Zero Readings'],
            ['key' => 'h_ref',           'label' => 'Reference gauge height', 'type' => 'number', 'unit' => 'mm', 'decimal_places' => 1, 'required' => false, 'group' => 'Head Correction'],
            ['key' => 'h_uuc',           'label' => 'UUC gauge height',       'type' => 'number', 'unit' => 'mm', 'decimal_places' => 1, 'required' => false, 'group' => 'Head Correction'],
        ];

        foreach ($setupFields as $f) {
            WorksheetField::create([
                'worksheet_template_id'  => $template->id,
                'key'                    => $f['key'],
                'label'                  => $f['label'],
                'type'                   => $f['type'],
                'unit'                   => $f['unit'] ?? null,
                'decimal_places'         => $f['decimal_places'] ?? 4,
                'options'                => $f['options'] ?? null,
                'required'               => $f['required'] ?? true,
                'repeats_per_test_point' => false,
                'sort_order'             => $sort++,
                'group'                  => $f['group'],
            ]);
        }

        // ── Per-test-point fields (repeat for each of 13 test points) ─
        // Engine reads: nominal_tp_{i}, uuc_c{n}_{pass}_tp_{i}, ref_c{n}_{pass}_tp_{i}
        // Passes: up1, dn1, up2, dn2  (for 2 cycles → 4 passes total)
        $tpFields = [
            ['key_tpl' => 'nominal_tp_{i}',       'label_tpl' => 'Nominal TP{n}',              'type' => 'number', 'decimal_places' => 3, 'group' => 'Test Points'],
            ['key_tpl' => 'uuc_c1_up1_tp_{i}',    'label_tpl' => 'UUC TP{n} Cycle 1 ↑',       'type' => 'number', 'decimal_places' => 4, 'group' => 'Test Points'],
            ['key_tpl' => 'uuc_c1_dn1_tp_{i}',    'label_tpl' => 'UUC TP{n} Cycle 1 ↓',       'type' => 'number', 'decimal_places' => 4, 'group' => 'Test Points'],
            ['key_tpl' => 'uuc_c2_up2_tp_{i}',    'label_tpl' => 'UUC TP{n} Cycle 2 ↑',       'type' => 'number', 'decimal_places' => 4, 'group' => 'Test Points'],
            ['key_tpl' => 'uuc_c2_dn2_tp_{i}',    'label_tpl' => 'UUC TP{n} Cycle 2 ↓',       'type' => 'number', 'decimal_places' => 4, 'group' => 'Test Points'],
            ['key_tpl' => 'ref_c1_up1_tp_{i}',    'label_tpl' => 'Ref TP{n} Cycle 1 ↑',       'type' => 'number', 'decimal_places' => 4, 'group' => 'Test Points'],
            ['key_tpl' => 'ref_c1_dn1_tp_{i}',    'label_tpl' => 'Ref TP{n} Cycle 1 ↓',       'type' => 'number', 'decimal_places' => 4, 'group' => 'Test Points'],
            ['key_tpl' => 'ref_c2_up2_tp_{i}',    'label_tpl' => 'Ref TP{n} Cycle 2 ↑',       'type' => 'number', 'decimal_places' => 4, 'group' => 'Test Points'],
            ['key_tpl' => 'ref_c2_dn2_tp_{i}',    'label_tpl' => 'Ref TP{n} Cycle 2 ↓',       'type' => 'number', 'decimal_places' => 4, 'group' => 'Test Points'],
        ];

        for ($i = 0; $i < $template->max_test_points; $i++) {
            foreach ($tpFields as $f) {
                WorksheetField::create([
                    'worksheet_template_id'  => $template->id,
                    'key'                    => str_replace('{i}', $i, $f['key_tpl']),
                    'label'                  => str_replace('{n}', $i + 1, $f['label_tpl']),
                    'type'                   => $f['type'],
                    'unit'                   => $f['unit'] ?? null,
                    'decimal_places'         => $f['decimal_places'],
                    'options'                => null,
                    'required'               => false,
                    'repeats_per_test_point' => true,
                    'sort_order'             => $sort++,
                    'group'                  => $f['group'],
                ]);
            }
        }

        $this->command->info('✓ Worksheet fields seeded (' . (count($setupFields) + count($tpFields) * $template->max_test_points) . ' fields)');

        // ── 4. CMC entries (from CMC sheet page 2) ───────────────────
        $template->cmcEntries()->delete();
        $cmcData = [
            // [from, to, medium, cmc_bar]
            [-1,   0,   'pneumatic',      0.001],
            [0,    4,   'pneumatic',      0.002],
            [4,    70,  'pneumatic',      0.003],
            [4,    70,  'hydraulic_oil',  0.004],
            [70,   300, 'hydraulic_oil',  0.005],
            [300,  700, 'hydraulic_oil',  0.006],
            [-1,   7,   'pneumatic',      0.001],
            [4,    70,  'hydraulic_water',0.007],
            [70,   300, 'hydraulic_water',0.008],
            [300,  700, 'hydraulic_water',0.009],
        ];
        foreach ($cmcData as [$from, $to, $medium, $cmc]) {
            CmcEntry::create([
                'worksheet_template_id' => $template->id,
                'range_from'            => $from,
                'range_to'              => $to,
                'unit'                  => 'bar',
                'medium'                => $medium,
                'cmc_value'             => $cmc,
            ]);
        }

        // ── 5. Seed master instruments from Master List sheet ────────
        $instruments = [
            [
                'lab_id'           => 'MCS-LAB-PG-001',
                'description'      => 'Working Standard Pressure Gauge',
                'make'             => 'ASHCROFT',
                'model'            => 'D1005PS',
                'serial_no'        => '3032115000',
                'instrument_type'  => 'pressure_gauge',
                'range_from'       => '-1',
                'range_to'         => '4',
                'unit'             => 'bar',
                'accuracy_pct_fs'  => 0.5,
                'mpe'              => 0.008,
                'resolution_positive' => 0.001,
                'resolution_negative' => 0.001,
                'accountable_drift'=> 0.001,
                'poly_coefficients'=> ['a'=>-0.000036289,'b'=>0.00038413,'c'=>1.0005,'d'=>-0.00036928],
                'poly_uncertainty_coefficients' => ['a'=>0,'b'=>0,'c'=>0,'d'=>0.002],
                'max_correction_residual'  => 0.00097509,
                'max_uncertainty_residual' => 0.001,
                'cal_certificate_no'       => 'PR/25/11/03',
                'cal_authority'            => 'Measurement Units, Standards & Services Department',
                'last_calibrated_at'       => '2025-11-01',
                'next_due_at'              => '2026-11-01',
                'cal_frequency_years'      => 1,
            ],
            [
                'lab_id'           => 'MCS-LAB-PG-002',
                'description'      => 'Reference Standard Pressure Gauge',
                'make'             => 'DRUCK',
                'model'            => 'DPI 104',
                'serial_no'        => '4139882',
                'instrument_type'  => 'pressure_gauge',
                'range_from'       => '-1',
                'range_to'         => '70',
                'unit'             => 'bar',
                'accuracy_pct_fs'  => 0.05,
                'mpe'              => 0.024,
                'resolution_positive' => 0.001,
                'resolution_negative' => 0.001,
                'accountable_drift'=> 0.008,
                'poly_coefficients'=> ['a'=>0,'b'=>0,'c'=>0,'d'=>4.6986e-8,'e'=>-6.6006e-6,'f'=>0.99993,'g'=>0.001973],
                'poly_uncertainty_coefficients' => ['a'=>0,'b'=>0,'c'=>0,'d'=>5.533e-6,'e'=>-9.836e-5,'f'=>6.3672e-4,'g'=>0.0037461],
                'max_correction_residual'  => 0.001036,
                'max_uncertainty_residual' => 0.001254,
                'cal_certificate_no'       => 'PR/25/08/09',
                'cal_authority'            => 'Measurement Units, Standards & Services Department',
                'last_calibrated_at'       => '2025-08-01',
                'next_due_at'              => '2027-08-01',
                'cal_frequency_years'      => 2,
            ],
            [
                'lab_id'           => 'MCS-LAB-PG-010',
                'description'      => 'Reference Standard Pressure Gauge',
                'make'             => 'DRUCK',
                'model'            => 'DPI 104',
                'serial_no'        => '3013562',
                'instrument_type'  => 'pressure_gauge',
                'range_from'       => '-1',
                'range_to'         => '7',
                'unit'             => 'bar',
                'accuracy_pct_fs'  => 0.05,
                'mpe'              => 0.0082,
                'resolution_positive' => 0.0001,
                'resolution_negative' => 0.001,
                'accountable_drift'=> 0.0079,
                'poly_coefficients'=> ['a'=>0,'b'=>0,'c'=>0,'d'=>4.1646e-7,'e'=>3.5983e-5,'f'=>1.0005,'g'=>2.8138e-4],
                'poly_uncertainty_coefficients' => ['a'=>-6.2963e-7,'b'=>1.3513e-5,'c'=>-1.0946e-4,'d'=>4.1258e-4,'e'=>-7.1019e-4,'f'=>4.3857e-4,'g'=>2.6871e-3],
                'max_correction_residual'  => 2.8138e-4,
                'max_uncertainty_residual' => 3.512e-5,
                'cal_certificate_no'       => 'PR/25/08/08',
                'cal_authority'            => 'Measurement Units, Standards & Services Department',
                'last_calibrated_at'       => '2025-08-01',
                'next_due_at'              => '2027-08-01',
                'cal_frequency_years'      => 2,
            ],
            [
                'lab_id'           => 'MCS-LAB-PG-011',
                'description'      => 'Working Standard Pressure Gauge',
                'make'             => 'Taishio',
                'model'            => 'TS-DPG-110',
                'serial_no'        => '25081101660005',
                'instrument_type'  => 'pressure_gauge',
                'range_from'       => '-1',
                'range_to'         => '70',
                'unit'             => 'bar',
                'accuracy_pct_fs'  => 0.2,
                'mpe'              => 0.03,
                'resolution_positive' => 0.01,
                'resolution_negative' => 0.01,
                'accountable_drift'=> 0.142,
                'poly_coefficients'=> ['a'=>0,'b'=>0,'c'=>0,'d'=>3.8094e-11,'e'=>-6.0558e-9,'f'=>2.4461e-7,'g'=>-7.1475e-3],
                'poly_uncertainty_coefficients' => ['a'=>6.3418e-12,'b'=>-1.1024e-9,'c'=>7.1335e-8,'d'=>-2.1059e-6,'e'=>2.7421e-5,'f'=>-1.1513e-4,'g'=>9.921e-3],
                'max_correction_residual'  => 0.0071475,
                'max_uncertainty_residual' => 1.1965e-4,
                'cal_certificate_no'       => 'PR/26/01/10',
                'cal_authority'            => 'Measurement Units, Standards & Services Department',
                'last_calibrated_at'       => '2026-01-10',
                'next_due_at'              => '2027-01-10',
                'cal_frequency_years'      => 1,
            ],
        ];

        foreach ($instruments as $data) {
            MasterInstrument::updateOrCreate(['lab_id' => $data['lab_id']], $data);
        }

        $this->command->info('✓ Pressure gauge worksheet template seeded');
        $this->command->info('✓ ' . count($instruments) . ' master instruments seeded');
        $this->command->info('✓ CMC entries seeded');
    }
}
