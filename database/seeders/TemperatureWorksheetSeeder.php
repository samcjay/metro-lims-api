<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorksheetTemplate;
use App\Models\WorksheetField;
use App\Models\MasterInstrument;
use App\Models\CmcEntry;
use App\Models\User;

class TemperatureWorksheetSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::create([
            'name'     => 'Seeder Admin',
            'email'    => 'seed@local',
            'password' => bcrypt('password'),
        ]);

        // ── 1. Worksheet template ────────────────────────────────────
        $template = WorksheetTemplate::updateOrCreate(
            ['code' => 'MCS-CAL-WS-02AN'],
            [
                'name'                => 'Worksheet for Analog/Digital Thermometers',
                'instrument_type'     => 'thermometer',
                'test_method_ref'     => 'MCS/TM/02',
                'unit'                => '°C',
                'max_test_points'     => 10,
                'cycles'              => 1,       // temperature uses multiple readings, not cycles
                'has_before_after'    => false,
                'has_head_correction' => false,
                'version'             => '1.0',
                'created_by'          => $user->id,
            ]
        );

        // ── 2. Formula nodes (11 uncertainty components) ─────────────
        // Source: CMC sheet + Analysis sheet of MCS-CAL-WS-02AN
        $nodes = [
            [
                'key'              => 'u_std_cal',
                'label'            => 'Standard calibration uncertainty',
                'symbol'           => 'δuSTD,CAL',
                'uncertainty_type' => 'B',
                'formula'          => '{mpe}',
                'depends_on'       => ['mpe'],
                'divisor_formula'  => '2',             // expanded U / k=2
                'sensitivity_coeff'=> 1,
            ],
            [
                'key'              => 'u_std_drift',
                'label'            => 'Drift of the reference/working standard',
                'symbol'           => 'δuSTD,DRIFT',
                'uncertainty_type' => 'B',
                'formula'          => '{drift}',
                'depends_on'       => ['drift'],
                'divisor_formula'  => 'sqrt(3)',
                'sensitivity_coeff'=> 1,
            ],
            [
                'key'              => 'u_std_res',
                'label'            => 'Resolution of the reference/working standard',
                'symbol'           => 'δuSTD,RES',
                'uncertainty_type' => 'B',
                'formula'          => '{resolution_ref}',
                'depends_on'       => ['resolution_ref'],
                'divisor_formula'  => '2*sqrt(3)',
                'sensitivity_coeff'=> 1,
            ],
            [
                'key'              => 'u_std_rep',
                'label'            => 'Repeatability of reference sensor readings',
                'symbol'           => 'δuSTD,REP',
                'uncertainty_type' => 'A',
                'formula'          => '{repeatability_ref}',
                'depends_on'       => ['repeatability_ref'],
                'divisor_formula'  => 'sqrt(6)',
                'sensitivity_coeff'=> 1,
            ],
            [
                'key'              => 'u_uuc_rep',
                'label'            => 'Repeatability of UUC readings',
                'symbol'           => 'δuUUC,REP',
                'uncertainty_type' => 'A',
                'formula'          => '{repeatability}',
                'depends_on'       => ['repeatability'],
                'divisor_formula'  => 'sqrt(6)',
                'sensitivity_coeff'=> 1,
            ],
            [
                'key'              => 'u_uuc_res',
                'label'            => 'Resolution of the UUC',
                'symbol'           => 'δuUUC,RES',
                'uncertainty_type' => 'B',
                'formula'          => '{resolution_uuc}',
                'depends_on'       => ['resolution_uuc'],
                'divisor_formula'  => '2*sqrt(3)',
                'sensitivity_coeff'=> 1,
            ],
            [
                'key'              => 'u_well_axi',
                'label'            => 'Axial uniformity of temperature source',
                'symbol'           => 'δuWELL,Axi',
                'uncertainty_type' => 'B',
                'formula'          => '{well_axial}',
                'depends_on'       => [],
                'divisor_formula'  => '2*sqrt(3)',
                'sensitivity_coeff'=> 1,
            ],
            [
                'key'              => 'u_well_rad',
                'label'            => 'Radial uniformity of temperature source',
                'symbol'           => 'δuWELL,Rad',
                'uncertainty_type' => 'B',
                'formula'          => '{well_radial}',
                'depends_on'       => [],
                'divisor_formula'  => '2*sqrt(3)',
                'sensitivity_coeff'=> 1,
            ],
            [
                'key'              => 'u_well_stab',
                'label'            => 'Stability of temperature source',
                'symbol'           => 'δuWELL,Stab',
                'uncertainty_type' => 'B',
                'formula'          => '{well_stability}',
                'depends_on'       => [],
                'divisor_formula'  => '2*sqrt(3)',
                'sensitivity_coeff'=> 1,
            ],
            [
                'key'              => 'u_res_cor',
                'label'            => 'Residual factor from correction curve',
                'symbol'           => 'δuResidual,Cor',
                'uncertainty_type' => 'B',
                'formula'          => '{poly_residual_cor}',
                'depends_on'       => ['poly_residual_cor'],
                'divisor_formula'  => 'sqrt(3)',
                'sensitivity_coeff'=> 1,
            ],
            [
                'key'              => 'u_res_un',
                'label'            => 'Residual factor from uncertainty curve',
                'symbol'           => 'δuResidual,Un',
                'uncertainty_type' => 'B',
                'formula'          => '{poly_residual_un}',
                'depends_on'       => ['poly_residual_un'],
                'divisor_formula'  => 'sqrt(3)',
                'sensitivity_coeff'=> 1,
            ],
        ];

        $template->formulaNodes()->delete();
        foreach ($nodes as $i => $node) {
            $template->formulaNodes()->create(array_merge($node, [
                'is_output'            => false,
                'scope_per_test_point' => true,
                'sort_order'           => $i,
            ]));
        }

        // ── 3. Worksheet fields ──────────────────────────────────────
        $template->fields()->delete();
        $sort = 0;

        // Setup fields (entered once per calibration)
        $setupFields = [
            ['key' => 'temperature_unit',   'label' => 'Temperature Unit',     'type' => 'select', 'options' => ['°C','°F','K'],           'group' => 'Setup'],
            ['key' => 'scale_type',         'label' => 'Scale Type',           'type' => 'select', 'options' => ['Digital','Analog'],       'group' => 'Setup'],
            ['key' => 'sensor_type',        'label' => 'Sensor Type',          'type' => 'select', 'options' => ['RTD','Thermocouple','Thermistor','Bimetallic'], 'group' => 'Setup'],
            ['key' => 'sensor_subtype',     'label' => 'Sensor Sub Type',      'type' => 'select', 'options' => ['PT100','PT1000','Type K','Type N','Type J','Type T','N/A'], 'group' => 'Setup'],
            ['key' => 'resolution_uuc',     'label' => 'UUC Resolution',       'type' => 'number', 'unit' => '°C', 'decimal_places' => 3,   'group' => 'Setup'],
            // Ambient conditions
            ['key' => 'ambient_temp_initial',     'label' => 'Initial Temperature',  'type' => 'number', 'unit' => '°C', 'decimal_places' => 1, 'required' => false, 'group' => 'Ambient Conditions'],
            ['key' => 'ambient_humidity_initial', 'label' => 'Initial Humidity',     'type' => 'number', 'unit' => '%',  'decimal_places' => 1, 'required' => false, 'group' => 'Ambient Conditions'],
            ['key' => 'ambient_temp_final',       'label' => 'Final Temperature',    'type' => 'number', 'unit' => '°C', 'decimal_places' => 1, 'required' => false, 'group' => 'Ambient Conditions'],
            ['key' => 'ambient_humidity_final',   'label' => 'Final Humidity',       'type' => 'number', 'unit' => '%',  'decimal_places' => 1, 'required' => false, 'group' => 'Ambient Conditions'],
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

        // Per-test-point fields
        // Each test point has: nominal, 6 ref readings, 6 UUC readings
        // Engine reads: nominal_tp_{i}, ref_tp_{i}_r{n}, uuc_tp_{i}_r{n}
        $tpFields = [
            ['key_tpl' => 'nominal_tp_{i}',    'label_tpl' => 'Nominal TP{n}',        'type' => 'number', 'decimal_places' => 1, 'group' => 'Test Points'],
            ['key_tpl' => 'ref_tp_{i}_r1',     'label_tpl' => 'Ref TP{n} Reading 1',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'ref_tp_{i}_r2',     'label_tpl' => 'Ref TP{n} Reading 2',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'ref_tp_{i}_r3',     'label_tpl' => 'Ref TP{n} Reading 3',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'ref_tp_{i}_r4',     'label_tpl' => 'Ref TP{n} Reading 4',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'ref_tp_{i}_r5',     'label_tpl' => 'Ref TP{n} Reading 5',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'ref_tp_{i}_r6',     'label_tpl' => 'Ref TP{n} Reading 6',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'uuc_tp_{i}_r1',     'label_tpl' => 'UUC TP{n} Reading 1',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'uuc_tp_{i}_r2',     'label_tpl' => 'UUC TP{n} Reading 2',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'uuc_tp_{i}_r3',     'label_tpl' => 'UUC TP{n} Reading 3',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'uuc_tp_{i}_r4',     'label_tpl' => 'UUC TP{n} Reading 4',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'uuc_tp_{i}_r5',     'label_tpl' => 'UUC TP{n} Reading 5',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
            ['key_tpl' => 'uuc_tp_{i}_r6',     'label_tpl' => 'UUC TP{n} Reading 6',  'type' => 'number', 'decimal_places' => 2, 'group' => 'Test Points'],
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

        $fieldCount = count($setupFields) + count($tpFields) * $template->max_test_points;
        $this->command->info("✓ Temperature worksheet fields seeded ({$fieldCount} fields)");

        // ── 4. CMC entries ───────────────────────────────────────────
        // From CMC sheet — entries per temperature range (°C)
        // CMC = U_expanded from budget. Approximate values from the analysis.
        $template->cmcEntries()->delete();
        $cmcData = [
            // [from, to, medium (dry_well model), cmc_°C]
            [-35,  100, 'DB-006', 2.0],
            [0,    100, 'DB-001', 2.0],
            [0,    140, 'DB-001', 2.0],
            [30,   250, 'DB-002', 2.0],
            [50,   500, 'DB-003', 2.0],
            [500,  600, 'DB-003', 3.0],
        ];
        foreach ($cmcData as [$from, $to, $medium, $cmc]) {
            CmcEntry::create([
                'worksheet_template_id' => $template->id,
                'range_from'            => $from,
                'range_to'              => $to,
                'unit'                  => '°C',
                'medium'                => $medium,
                'cmc_value'             => $cmc,
            ]);
        }
        $this->command->info('✓ Temperature CMC entries seeded');

        // ── 5. Master instruments — RTD sensors / thermometers ───────
        // From Master List + Ref.Data sheets of MCS-2026-1185-004.xlsx
        $instruments = [
            // RTD Working Standards
            [
                'lab_id'              => 'MCS-LAB-RTD-001',
                'description'         => 'Working Standard PRT Sensor',
                'make'                => 'HNR',
                'model'               => '845-202',
                'serial_no'           => 'not known',
                'instrument_type'     => 'thermometer',
                'range_from'          => '-8',
                'range_to'            => '110',
                'unit'                => '°C',
                'accuracy_pct_fs'     => 0,
                'mpe'                 => 1.16,        // from Ref.Data R27
                'resolution_positive' => 0.001,       // <100°C
                'resolution_negative' => 0.001,
                'accountable_drift'   => 0.54,        // from Ref.Data R27 K:0.54
                'poly_coefficients'             => [],
                'poly_uncertainty_coefficients' => [],
                'max_correction_residual'       => 0,
                'max_uncertainty_residual'      => 0,
                'cal_certificate_no'  => 'LCS/CR/25/10846',
                'cal_authority'       => 'Lanka Calibration Services Pvt Ltd',
                'last_calibrated_at'  => '2025-06-11',  // Excel 45798
                'next_due_at'         => '2026-06-11',  // Excel 46162 ≈ +1 year
                'cal_frequency_years' => 1,
                'is_active'           => true,
            ],
            [
                'lab_id'              => 'MCS-LAB-RTD-002',
                'description'         => 'Working Standard PRT Sensor',
                'make'                => 'HNR',
                'model'               => '845-203',
                'serial_no'           => 'not known',
                'instrument_type'     => 'thermometer',
                'range_from'          => '30',
                'range_to'            => '250',
                'unit'                => '°C',
                'accuracy_pct_fs'     => 0,
                'mpe'                 => 1.55,        // Class B: ±(0.30+0.005×250) = 1.55
                'resolution_positive' => 0.01,
                'resolution_negative' => 0.01,
                'accountable_drift'   => 0.13,        // max drift from Ref.Data R79 K:0.13
                'poly_coefficients'             => [],
                'poly_uncertainty_coefficients' => [],
                'max_correction_residual'       => 0,
                'max_uncertainty_residual'      => 0,
                'cal_certificate_no'  => 'LCS/CR/26/10062',
                'cal_authority'       => 'Lanka Calibration Services Pvt Ltd',
                'last_calibrated_at'  => '2026-01-20',  // Excel 46041
                'next_due_at'         => '2026-12-21',  // Excel 46405 ≈ +1 year
                'cal_frequency_years' => 1,
                'is_active'           => true,
            ],
            [
                'lab_id'              => 'MCS-LAB-RTD-003',
                'description'         => 'Working Standard PRT Sensor',
                'make'                => 'HNR',
                'model'               => '845-204',
                'serial_no'           => 'not known',
                'instrument_type'     => 'thermometer',
                'range_from'          => '30',
                'range_to'            => '650',
                'unit'                => '°C',
                'accuracy_pct_fs'     => 0,
                'mpe'                 => 3.55,        // Class B at 650°C: ±(0.30+0.005×650)=3.55
                'resolution_positive' => 0.01,
                'resolution_negative' => 0.01,
                'accountable_drift'   => 0,
                'poly_coefficients'             => [],
                'poly_uncertainty_coefficients' => [],
                'max_correction_residual'       => 0,
                'max_uncertainty_residual'      => 0,
                'cal_certificate_no'  => 'LCS/CR/25/11284',
                'cal_authority'       => 'Lanka Calibration Services Pvt Ltd',
                'last_calibrated_at'  => '2025-08-11',  // Excel 45859
                'next_due_at'         => '2026-08-11',  // Excel 46223 ≈ +1 year
                'cal_frequency_years' => 1,
                'is_active'           => true,
            ],
            [
                'lab_id'              => 'MCS-LAB-RTD-005',
                'description'         => 'Working Standard PRT Sensor',
                'make'                => 'ISOTECH',
                'model'               => '935-14-61',
                'serial_no'           => '37729/1',
                'instrument_type'     => 'thermometer',
                'range_from'          => '-40',
                'range_to'            => '250',
                'unit'                => '°C',
                'accuracy_pct_fs'     => 0,
                'mpe'                 => 0.65,        // Class A
                'resolution_positive' => 0.1,
                'resolution_negative' => 0.1,
                'accountable_drift'   => 0,
                'poly_coefficients'             => [],
                'poly_uncertainty_coefficients' => [],
                'max_correction_residual'       => 0,
                'max_uncertainty_residual'      => 0,
                'cal_certificate_no'  => 'TH/25/02/12',
                'cal_authority'       => 'Lanka Calibration Services Pvt Ltd',
                'last_calibrated_at'  => '2025-03-29',  // Excel 45723
                'next_due_at'         => '2026-03-29',  // Excel 46087 ≈ +1 year
                'cal_frequency_years' => 1,
                'is_active'           => true,
            ],
            // Working / Reference Standard Thermometers
            [
                'lab_id'              => 'MCS-LAB-THM-003',
                'description'         => 'Working Standard Thermometer',
                'make'                => 'ACEZ',
                'model'               => '',
                'serial_no'           => 'WOC1995-1',
                'instrument_type'     => 'thermometer',
                'range_from'          => '-40',
                'range_to'            => '600',
                'unit'                => '°C',
                'accuracy_pct_fs'     => 0,
                'mpe'                 => 1.35,        // Class A accuracy from master list
                'resolution_positive' => 0.001,
                'resolution_negative' => 0.001,
                'accountable_drift'   => 0,
                'poly_coefficients'             => [],
                'poly_uncertainty_coefficients' => [],
                'max_correction_residual'       => 0,
                'max_uncertainty_residual'      => 0,
                'cal_certificate_no'  => 'TH/26/01/04',
                'cal_authority'       => 'Lanka Calibration Services Pvt Ltd',
                'last_calibrated_at'  => '2026-02-20',  // Excel 46072
                'next_due_at'         => '2027-01-21',  // Excel 46436
                'cal_frequency_years' => 1,
                'is_active'           => true,
            ],
            [
                'lab_id'              => 'MCS-LAB-THM-004',
                'description'         => 'Reference Standard Thermometer',
                'make'                => 'ISOTECH',
                'model'               => '935-14-95H',
                'serial_no'           => '451119/2',
                'instrument_type'     => 'thermometer',
                'range_from'          => '-40',
                'range_to'            => '600',
                'unit'                => '°C',
                'accuracy_pct_fs'     => 0,
                'mpe'                 => 0.01,        // 10 mK from master list
                'resolution_positive' => 0.001,
                'resolution_negative' => 0.001,
                'accountable_drift'   => 0,
                'poly_coefficients'             => [],
                'poly_uncertainty_coefficients' => [],
                'max_correction_residual'       => 0,
                'max_uncertainty_residual'      => 0,
                'cal_certificate_no'  => 'TH/26/01/03',
                'cal_authority'       => 'Lanka Calibration Services Pvt Ltd',
                'last_calibrated_at'  => '2026-02-19',  // Excel 46071
                'next_due_at'         => '2028-01-19',  // Excel 46800 (2-year interval)
                'cal_frequency_years' => 2,
                'is_active'           => true,
            ],
            // Dry Wells (temperature sources — treated as instruments in the system)
            [
                'lab_id'              => 'MCS-LAB-DB-001',
                'description'         => 'Dry Well (-20°C to 140°C)',
                'make'                => 'ISOTECH',
                'model'               => 'EUROPA 4520',
                'serial_no'           => '38119/1',
                'instrument_type'     => 'dry_well',
                'range_from'          => '-20',
                'range_to'            => '140',
                'unit'                => '°C',
                'accuracy_pct_fs'     => 0,
                'mpe'                 => 0.03,
                'resolution_positive' => 0.01,
                'resolution_negative' => 0.01,
                'accountable_drift'   => 0,
                'poly_coefficients'             => [],
                'poly_uncertainty_coefficients' => [],
                'max_correction_residual'       => 0,
                'max_uncertainty_residual'      => 0,
                'cal_certificate_no'  => 'LCS/CR/23/10364',
                'cal_authority'       => 'Lanka Calibration Services Pvt Ltd',
                'last_calibrated_at'  => '2023-05-17',  // Excel 45056
                'next_due_at'         => '2026-05-10',  // Excel 46150 (~3 years)
                'cal_frequency_years' => 3,
                'is_active'           => true,
            ],
            [
                'lab_id'              => 'MCS-LAB-DB-002',
                'description'         => 'Dry Well (30°C to 250°C)',
                'make'                => 'ISOTECH',
                'model'               => 'CALISTO 4593',
                'serial_no'           => '38119/2',
                'instrument_type'     => 'dry_well',
                'range_from'          => '20',
                'range_to'            => '250',
                'unit'                => '°C',
                'accuracy_pct_fs'     => 0,
                'mpe'                 => 0.03,
                'resolution_positive' => 0.01,
                'resolution_negative' => 0.01,
                'accountable_drift'   => 0,
                'poly_coefficients'             => [],
                'poly_uncertainty_coefficients' => [],
                'max_correction_residual'       => 0,
                'max_uncertainty_residual'      => 0,
                'cal_certificate_no'  => 'LCS/CR/26/10061',
                'cal_authority'       => 'Lanka Calibration Services Pvt Ltd',
                'last_calibrated_at'  => '2026-01-20',  // Excel 46041
                'next_due_at'         => '2029-01-20',  // Excel 47135 (~3 years)
                'cal_frequency_years' => 3,
                'is_active'           => true,
            ],
            [
                'lab_id'              => 'MCS-LAB-DB-003',
                'description'         => 'Dry Well (50°C to 500°C)',
                'make'                => 'ISOTECH',
                'model'               => 'FAST-CAL 907 High',
                'serial_no'           => '38119/3',
                'instrument_type'     => 'dry_well',
                'range_from'          => '50',
                'range_to'            => '550',
                'unit'                => '°C',
                'accuracy_pct_fs'     => 0,
                'mpe'                 => 0.5,
                'resolution_positive' => 0.01,
                'resolution_negative' => 0.01,
                'accountable_drift'   => 0,
                'poly_coefficients'             => [],
                'poly_uncertainty_coefficients' => [],
                'max_correction_residual'       => 0,
                'max_uncertainty_residual'      => 0,
                'cal_certificate_no'  => 'LCS/CR/23/10572',
                'cal_authority'       => 'Lanka Calibration Services Pvt Ltd',
                'last_calibrated_at'  => '2023-06-23',  // Excel 45089
                'next_due_at'         => '2026-06-11',  // Excel 46183 (~3 years)
                'cal_frequency_years' => 3,
                'is_active'           => true,
            ],
            [
                'lab_id'              => 'MCS-LAB-DB-006',
                'description'         => 'Dry Well (-35°C to 100°C)',
                'make'                => 'HSIN',
                'model'               => 'HSIN150C',
                'serial_no'           => '15025032',
                'instrument_type'     => 'dry_well',
                'range_from'          => '-35',
                'range_to'            => '100',
                'unit'                => '°C',
                'accuracy_pct_fs'     => 0,
                'mpe'                 => 0.1,
                'resolution_positive' => 0.01,
                'resolution_negative' => 0.01,
                'accountable_drift'   => 0,
                'poly_coefficients'             => [],
                'poly_uncertainty_coefficients' => [],
                'max_correction_residual'       => 0,
                'max_uncertainty_residual'      => 0,
                'cal_certificate_no'  => 'INTERNAL',
                'cal_authority'       => 'Internal',
                'last_calibrated_at'  => null,
                'next_due_at'         => null,
                'cal_frequency_years' => 1,
                'is_active'           => false,   // marked internal/inactive
            ],
        ];

        foreach ($instruments as $data) {
            MasterInstrument::updateOrCreate(['lab_id' => $data['lab_id']], $data);
        }

        $this->command->info('✓ Temperature reference instruments seeded (' . count($instruments) . ' instruments)');
        $this->command->info('');
        $this->command->info('  RTD sensors:  RTD-001, 002, 003, 005');
        $this->command->info('  Thermometers: THM-003 (WS), THM-004 (REF)');
        $this->command->info('  Dry wells:    DB-001, 002, 003, 006');
    }
}
