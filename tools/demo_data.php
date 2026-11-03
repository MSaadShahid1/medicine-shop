<?php
/** CLI-only, additive demo data. Usage and removal precautions: DEMO_DATA.md. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Demo data management is available from the command line only.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once dirname(__DIR__) . '/config.php';
date_default_timezone_set('Asia/Karachi');

$tables = ['companies', 'customers', 'products', 'purchase_bills', 'purchase_bill_items', 'sales_bills', 'sales_bill_items', 'payments'];
$stateDir = dirname(__DIR__, 3) . '/Farms-data-backups';
if (!is_dir($stateDir) && !mkdir($stateDir, 0700, true)) throw new RuntimeException('Cannot create the private demo state directory.');
$manifestPath = $stateDir . '/medicine_shop-demo-manifest.json';
$lock = fopen($stateDir . '/medicine_shop-demo.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another demo operation is running.');
$command = $argv[1] ?? 'help';

function demoRows(mysqli $conn, string $table): array {
    $rows = [];
    $result = $conn->query('SELECT * FROM `' . $table . '` ORDER BY id');
    while ($row = $result->fetch_assoc()) $rows[(int) $row['id']] = $row;
    return $rows;
}
function demoHash(array $row): string {
    ksort($row);
    return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
}
function demoFingerprints(mysqli $conn, array $tables): array {
    $snapshot = [];
    foreach ($tables as $table) {
        $snapshot[$table] = [];
        foreach (demoRows($conn, $table) as $id => $row) $snapshot[$table][$id] = demoHash($row);
    }
    return $snapshot;
}
function demoInsert(mysqli $conn, string $table, array $data, array &$records): int {
    $columns = array_keys($data);
    $values = array_values($data);
    $types = '';
    foreach ($values as $value) $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();
    $records[$table][] = $id;
    return $id;
}
function demoOwnedIds(array $manifest, string $table): string {
    return implode(',', array_map('intval', $manifest['records'][$table] ?: [0]));
}
function demoVerify(mysqli $conn, array $manifest, bool $originals = true): array {
    if (($manifest['database'] ?? '') !== DB_NAME || ($manifest['host'] ?? '') !== DB_HOST) throw new RuntimeException('Manifest does not match this database connection.');
    $problems = [];
    foreach ($manifest['records'] as $table => $ids) {
        $rows = demoRows($conn, $table);
        foreach ($ids as $id) {
            if (!isset($rows[$id])) $problems[] = "$table #$id is missing";
            elseif (demoHash($rows[$id]) !== $manifest['fingerprints'][$table][$id]) $problems[] = "$table #$id was changed";
        }
        if ($originals) foreach ($manifest['originals'][$table] as $id => $hash) {
            if (!isset($rows[$id]) || demoHash($rows[$id]) !== $hash) $problems[] = "Original $table #$id was changed";
        }
    }
    return $problems;
}
function demoAssertRelations(mysqli $conn, array $manifest): void {
    $purchases = demoOwnedIds($manifest, 'purchase_bills');
    $sales = demoOwnedIds($manifest, 'sales_bills');
    $products = demoOwnedIds($manifest, 'products');
    $bad = [];
    $checks = [
        'purchase item totals' => "SELECT COUNT(*) AS n FROM purchase_bill_items WHERE purchase_bill_id IN ($purchases) AND total <> quantity * rate",
        'sales item totals' => "SELECT COUNT(*) AS n FROM sales_bill_items WHERE sales_bill_id IN ($sales) AND total <> quantity * rate",
        'purchase balances' => "SELECT COUNT(*) AS n FROM purchase_bills WHERE id IN ($purchases) AND (total_amount <> paid_amount + balance OR paid_amount <> (SELECT COALESCE(SUM(amount),0) FROM payments WHERE purchase_bill_id=purchase_bills.id))",
        'purchase bill totals' => "SELECT COUNT(*) AS n FROM purchase_bills pb WHERE id IN ($purchases) AND bill_number NOT LIKE 'DEMO-OPEN-%' AND total_amount <> (SELECT COALESCE(SUM(total),0) FROM purchase_bill_items WHERE purchase_bill_id=pb.id)",
        'sales bill totals' => "SELECT COUNT(*) AS n FROM sales_bills sb WHERE id IN ($sales) AND total_amount <> (SELECT COALESCE(SUM(total),0) FROM sales_bill_items WHERE sales_bill_id=sb.id)",
        'product stock' => "SELECT COUNT(*) AS n FROM products p WHERE id IN ($products) AND (current_stock < 0 OR current_stock <> (SELECT COALESCE(SUM(quantity),0) FROM purchase_bill_items WHERE product_id=p.id) - (SELECT COALESCE(SUM(quantity),0) FROM sales_bill_items WHERE product_id=p.id))",
        'cross-supplier purchase items' => "SELECT COUNT(*) AS n FROM purchase_bill_items i JOIN purchase_bills b ON b.id=i.purchase_bill_id JOIN products p ON p.id=i.product_id WHERE b.id IN ($purchases) AND b.company_id <> p.company_id",
    ];
    foreach ($checks as $name => $sql) if ((int) $conn->query($sql)->fetch_assoc()['n'] !== 0) $bad[] = $name;
    if ($bad) throw new RuntimeException('Demo consistency check failed: ' . implode(', ', $bad));
}
function demoSaveManifest(string $path, array $manifest): void {
    $temporary = $path . '.tmp';
    if (file_put_contents($temporary, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temporary, $path)) throw new RuntimeException('Cannot save private ownership manifest.');
}
function demoPrintSummary(array $manifest): void {
    foreach ($manifest['records'] as $table => $ids) echo str_pad($table, 22) . count($ids) . PHP_EOL;
    echo 'Preview links:' . PHP_EOL;
    foreach ($manifest['links'] as $label => $url) echo "  $label: http://localhost/Farms/$url" . PHP_EOL;
}

try {
    if (!in_array($command, ['seed', 'verify', 'remove'], true)) {
        echo "Usage:\n  php tools/demo_data.php seed --backup=C:\\path\\before-demo.sql\n  php tools/demo_data.php verify\n  php tools/demo_data.php remove --dry-run\n  php tools/demo_data.php remove --confirm-demo-only\n";
        exit(0);
    }
    if (is_file($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        if ($command === 'seed') {
            $problems = demoVerify($conn, $manifest);
            if ($problems) throw new RuntimeException('Demo data already has a manifest. Refusing duplicate inserts. ' . implode('; ', $problems));
            demoAssertRelations($conn, $manifest);
            echo "Demo data already exists; no duplicate rows were inserted.\n";
            demoPrintSummary($manifest);
            exit(0);
        }
    } elseif ($command !== 'seed') {
        throw new RuntimeException('No active demo ownership manifest exists. Nothing will be changed.');
    }

    if ($command === 'verify') {
        $problems = demoVerify($conn, $manifest);
        if ($problems) throw new RuntimeException(implode('; ', $problems));
        demoAssertRelations($conn, $manifest);
        echo "PASS: original rows unchanged; demo rows, stock, item totals, balances and payment totals match.\n";
        demoPrintSummary($manifest);
        exit(0);
    }
    if ($command === 'remove') {
        $dryRun = in_array('--dry-run', $argv, true);
        if (!$dryRun && !in_array('--confirm-demo-only', $argv, true)) throw new RuntimeException('Removal requires --dry-run or --confirm-demo-only. Only manifest-owned rows are eligible.');
        $conn->begin_transaction();
        try {
            // Lock all relevant rows so changes cannot race the ownership checks.
            foreach ($tables as $table) $conn->query('SELECT id FROM `' . $table . '` FOR UPDATE')->free();
            $problems = demoVerify($conn, $manifest, false);
            if ($problems) throw new RuntimeException('Refusing removal of changed data: ' . implode('; ', $problems));
            $companies = demoOwnedIds($manifest, 'companies');
            $customers = demoOwnedIds($manifest, 'customers');
            $products = demoOwnedIds($manifest, 'products');
            $purchases = demoOwnedIds($manifest, 'purchase_bills');
            $sales = demoOwnedIds($manifest, 'sales_bills');
            $dependencies = [
                ['products', "company_id IN ($companies)"],
                ['purchase_bills', "company_id IN ($companies)"],
                ['sales_bills', "customer_id IN ($customers)"],
                ['purchase_bill_items', "(purchase_bill_id IN ($purchases) OR product_id IN ($products))"],
                ['sales_bill_items', "(sales_bill_id IN ($sales) OR product_id IN ($products))"],
                ['payments', "purchase_bill_id IN ($purchases)"],
            ];
            foreach ($dependencies as [$table, $condition]) {
                $owned = demoOwnedIds($manifest, $table);
                if ((int) $conn->query("SELECT COUNT(*) AS n FROM `$table` WHERE $condition AND id NOT IN ($owned)")->fetch_assoc()['n'] > 0) throw new RuntimeException("New records in $table reference demo data. Refusing removal to protect your work.");
            }
            if ($dryRun) {
                $conn->rollback();
                echo "PASS: safe removal checks passed. Dry run only; no data was deleted.\n";
                demoPrintSummary($manifest);
                exit(0);
            }
            foreach (['payments', 'purchase_bill_items', 'sales_bill_items', 'purchase_bills', 'sales_bills', 'products', 'customers', 'companies'] as $table) {
                $owned = demoOwnedIds($manifest, $table);
                $conn->query("DELETE FROM `$table` WHERE id IN ($owned)");
            }
            $conn->commit();
        } catch (Throwable $error) { $conn->rollback(); throw $error; }
        rename($manifestPath, $manifestPath . '.removed-' . date('Ymd-His'));
        echo "Removed only unchanged, manifest-owned demo rows. No original rows were deleted. Auto-increment counters were not reset.\n";
        exit(0);
    }

    $backup = '';
    foreach ($argv as $argument) if (strpos($argument, '--backup=') === 0) $backup = substr($argument, 9);
    if (!$backup || !is_file($backup) || filesize($backup) < 100) throw new RuntimeException('A successful pre-seed SQL backup is required via --backup=absolute-path.sql.');
    // Never reuse a previous, untracked demo prefix or mutate existing records.
    foreach (['companies' => 'company_name', 'customers' => 'customer_name', 'products' => 'product_name', 'purchase_bills' => 'bill_number', 'sales_bills' => 'bill_number'] as $table => $column) {
        if ((int) $conn->query("SELECT COUNT(*) AS n FROM `$table` WHERE `$column` LIKE '[DEMO]%' OR `$column` LIKE 'DEMO-%'")->fetch_assoc()['n']) throw new RuntimeException('Untracked demo-labelled records already exist. No data was inserted.');
    }
    $records = array_fill_keys($tables, []);
    $originals = demoFingerprints($conn, $tables);
    $monthStart = new DateTimeImmutable('first day of this month');
    $today = new DateTimeImmutable('today');
    $start = $monthStart->modify('-5 months');
    $created = $start->format('Y-m-d') . ' 09:00:00';
    $conn->begin_transaction();
    $manifestWritten = false;
    try {
        $suppliers = [
            ['Crescent Pharma', 'Hamza Ali', 'Lahore'], ['Northstar Healthcare', 'Sara Ahmed', 'Islamabad'],
            ['VitalLeaf Supplies', 'Usman Raza', 'Karachi'], ['Riverbend Medical', 'Nadia Iqbal', 'Rawalpindi'],
            ['Solace Laboratories', 'Bilal Hassan', 'Multan'], ['Brightline Distributors', 'Hina Shah', 'Faisalabad'],
        ];
        $companyIds = [];
        foreach ($suppliers as $i => $supplier) $companyIds[] = demoInsert($conn, 'companies', ['company_name' => '[DEMO] ' . $supplier[0], 'contact_person' => $supplier[1] . ' (demo)', 'phone' => '0000-000' . (1001 + $i), 'address' => 'Fictional demo office, ' . $supplier[2], 'created_at' => $created], $records);
        $names = ['Amina Farooq', 'Zain Malik', 'Hassan Khan', 'Maryam Noor', 'Omar Siddiqui', 'Fatima Raza', 'Danish Ali', 'Sana Mir', 'Ibrahim Shah', 'Hira Ahmed', 'Yusuf Abbas', 'Noor Iqbal'];
        $customers = [];
        foreach ($names as $i => $name) {
            $customer = ['customer_name' => '[DEMO] ' . $name, 'customer_phone' => '0000-000' . (2001 + $i), 'address' => 'Fictional demo address, ' . $suppliers[$i % 6][2], 'created_at' => $created];
            $customer['id'] = demoInsert($conn, 'customers', $customer, $records);
            $customers[] = $customer;
        }
        // Money is calculated in integer paisa. These are fictional display samples.
        $catalog = [
            ['Calmora 500mg Tablets', 'Paracetamol 500mg', 4500, 6500], ['Relivon 200mg Tablets', 'Ibuprofen 200mg', 8000, 11500],
            ['ClearDay 10mg Tablets', 'Cetirizine 10mg', 5500, 8500], ['HydraPlus ORS Sachets', 'Oral rehydration salts', 3000, 4500],
            ['Citriva C Tablets', 'Ascorbic acid 500mg', 9500, 14000], ['Sunwell D3 Capsules', 'Cholecalciferol', 18000, 25000],
            ['Zinova Zinc Tablets', 'Zinc supplement', 8500, 12500], ['FolateCare Tablets', 'Folic acid', 4000, 6000],
            ['SootheMist Saline Spray', 'Sodium chloride 0.65%', 16000, 22500], ['DermaSoft Cream 30g', 'Moisturising cream', 14500, 21000],
            ['FreshEye Drops 10ml', 'Lubricating eye drops', 17500, 24500], ['CoolEase Gel 30g', 'Topical cooling gel', 12500, 18500],
            ['VitaBloom Multivitamin', 'Multivitamin supplement', 22000, 31000], ['MineralPlus Tablets', 'Calcium and magnesium', 19500, 27500],
            ['GentleIron Capsules', 'Iron supplement', 15500, 22000], ['ProBalance Sachets', 'Probiotic supplement', 13500, 19500],
            ['PureGuard Hand Wash', 'Liquid hand wash 250ml', 11500, 16500], ['CareShield Sanitizer', 'Hand sanitizer 100ml', 6500, 9500],
            ['SoftWrap Gauze Pack', 'Sterile gauze dressing', 3500, 5500], ['FlexCare Bandage', 'Elastic support bandage', 7000, 10500],
            ['FirstAid Cotton 100g', 'Absorbent cotton wool', 8500, 12500], ['SkinSafe Wipes Pack', 'Cleansing wipes', 6000, 9000],
            ['DailyCare Saline 100ml', 'Sodium chloride solution', 9000, 13000], ['Medibox Dressing Kit', 'Basic dressing supplies', 24000, 34000],
        ];
        $targets = [0,4,8,12,18,0,22,35,48,60,75,90,26,32,65,110,140,200,28,44,72,86,120,160];
        $products = [];
        foreach ($catalog as $i => $entry) {
            $product = ['product_name' => '[DEMO] ' . $entry[0], 'composition' => $entry[1], 'company_id' => $companyIds[intdiv($i,4)], 'purchase_rate' => $entry[2]/100, 'sale_rate' => $entry[3]/100, 'current_stock' => 0, 'created_at' => $created];
            $product['id'] = demoInsert($conn, 'products', $product, $records);
            $products[] = $product;
        }
        $showcaseSale = 0;
        $showcasePurchase = 0;
        for ($month = 0; $month < 6; $month++) {
            $anchor = $start->modify('+' . $month . ' months');
            $lastDay = $month === 5 ? (int) $today->format('j') : min(27, (int) $anchor->format('t'));
            $purchaseDay = min(2, $lastDay);
            $purchaseDate = $anchor->setDate((int)$anchor->format('Y'), (int)$anchor->format('m'), $purchaseDay)->format('Y-m-d');
            $salesPlan = [];
            $monthlySold = array_fill(0, 24, 0);
            for ($billIndex=0; $billIndex<12; $billIndex++) {
                $lines = [];
                $lineCount = $month === 5 && $billIndex === 11 ? 12 : 4;
                for ($line=0; $line<$lineCount; $line++) {
                    $index = ($billIndex*2 + $line*5 + $month) % 24;
                    $qty = 1 + (($billIndex + $line) % 5) + $month;
                    $lines[] = ['index' => $index, 'quantity' => $qty, 'cents' => $qty * $catalog[$index][3]];
                    $monthlySold[$index] += $qty;
                }
                $salesPlan[] = $lines;
            }
            foreach ($companyIds as $companyIndex => $companyId) {
                $lines = [];
                $total = 0;
                for ($i=$companyIndex*4; $i<$companyIndex*4+4; $i++) {
                    $qty = $monthlySold[$i] + ($month === 0 ? $targets[$i] : 0);
                    if (!$qty) continue;
                    $amount = $qty * $catalog[$i][2];
                    $total += $amount;
                    $lines[] = ['index'=>$i,'quantity'=>$qty,'cents'=>$amount];
                }
                $mode = ($month + $companyIndex) % 3;
                $paid = $mode === 0 ? $total : ($mode === 1 ? (int)round($total/2) : 0);
                $reference = 'DEMO-' . $anchor->format('Ym') . '-PUR-' . sprintf('%02d', $companyIndex+1);
                $purchaseId = demoInsert($conn, 'purchase_bills', ['bill_number'=>$reference,'company_id'=>$companyId,'total_amount'=>$total/100,'paid_amount'=>$paid/100,'balance'=>($total-$paid)/100,'bill_date'=>$purchaseDate,'created_at'=>$purchaseDate.' 09:00:00'], $records);
                foreach ($lines as $line) demoInsert($conn, 'purchase_bill_items', ['purchase_bill_id'=>$purchaseId,'product_id'=>$products[$line['index']]['id'],'quantity'=>$line['quantity'],'rate'=>$catalog[$line['index']][2]/100,'total'=>$line['cents']/100], $records);
                if ($paid) {
                    $first = (int)round($paid*0.4);
                    foreach ([$first,$paid-$first] as $paymentIndex=>$amount) {
                        $day = min($purchaseDay + $paymentIndex*2, $lastDay);
                        $date = $anchor->setDate((int)$anchor->format('Y'), (int)$anchor->format('m'), $day)->format('Y-m-d');
                        demoInsert($conn, 'payments', ['purchase_bill_id'=>$purchaseId,'amount'=>$amount/100,'payment_date'=>$date,'notes'=>'[DEMO] '.($paymentIndex ? 'Follow-up payment' : 'Initial payment').' for '.$reference.'. Fictional transaction.','created_at'=>$date.' 10:00:00'], $records);
                    }
                }
                if ($month === 5 && $mode === 1) $showcasePurchase = $purchaseId;
            }
            foreach ($salesPlan as $billIndex=>$lines) {
                $day = min($lastDay, 3 + (int)floor($billIndex*max(0,$lastDay-3)/11));
                $date = $anchor->setDate((int)$anchor->format('Y'), (int)$anchor->format('m'), $day)->format('Y-m-d');
                $customer = $customers[($billIndex+$month)%12];
                $total = array_sum(array_column($lines,'cents'));
                $saleId = demoInsert($conn, 'sales_bills', ['customer_id'=>$customer['id'],'bill_number'=>'DEMO-'.$anchor->format('Ym').'-INV-'.sprintf('%02d',$billIndex+1),'customer_name'=>$customer['customer_name'],'customer_phone'=>$customer['customer_phone'],'customer_address'=>$customer['address'],'total_amount'=>$total/100,'bill_date'=>$date,'created_at'=>$date.' 14:00:00'], $records);
                foreach ($lines as $line) demoInsert($conn, 'sales_bill_items', ['sales_bill_id'=>$saleId,'product_id'=>$products[$line['index']]['id'],'quantity'=>$line['quantity'],'rate'=>$catalog[$line['index']][3]/100,'total'=>$line['cents']/100], $records);
                if ($month===5 && $billIndex===11) $showcaseSale = $saleId;
            }
        }
        // Opening balances intentionally have no stock items, matching the app's model.
        foreach ([1200000,1850000,760000] as $i=>$total) {
            $paid = [450000,0,760000][$i];
            $date = $start->modify('-1 month')->format('Y-m-d');
            $purchaseId=demoInsert($conn,'purchase_bills',['bill_number'=>'DEMO-OPEN-'.sprintf('%02d',$i+1),'company_id'=>$companyIds[$i],'total_amount'=>$total/100,'paid_amount'=>$paid/100,'balance'=>($total-$paid)/100,'bill_date'=>$date,'created_at'=>$date.' 09:00:00'],$records);
            if($paid) demoInsert($conn,'payments',['purchase_bill_id'=>$purchaseId,'amount'=>$paid/100,'payment_date'=>$date,'notes'=>'[DEMO] Historical opening-balance payment. Fictional transaction.','created_at'=>$date.' 10:00:00'],$records);
        }
        // Derive stocks from their actual inserted purchase/sale movements.
        foreach ($products as $i=>$product) {
            $id=(int)$product['id'];
            $conn->query("UPDATE products SET current_stock=(SELECT COALESCE(SUM(quantity),0) FROM purchase_bill_items WHERE product_id=$id)-(SELECT COALESCE(SUM(quantity),0) FROM sales_bill_items WHERE product_id=$id) WHERE id=$id");
            $stock=(int)$conn->query("SELECT current_stock FROM products WHERE id=$id")->fetch_assoc()['current_stock'];
            if($stock!==$targets[$i]) throw new RuntimeException('Calculated stock differs from the planned demo state.');
        }
        $fingerprints=[];
        foreach($tables as $table) {
            $fingerprints[$table]=[];
            foreach(demoRows($conn,$table) as $id=>$row) if(in_array($id,$records[$table],true)) $fingerprints[$table][$id]=demoHash($row);
        }
        $manifest=['version'=>1,'database'=>DB_NAME,'host'=>DB_HOST,'created_at'=>date(DATE_ATOM),'backup'=>realpath($backup),'records'=>$records,'fingerprints'=>$fingerprints,'originals'=>$originals,'links'=>[
            'Dashboard'=>'dashboard.php','Suppliers'=>'view_companies.php','Customers'=>'view_customers.php','Products'=>'view_products.php',
            'Supplier ledger'=>'company_details.php?id='.$companyIds[0], 'Customer ledger'=>'customer_ledger.php?id='.$customers[4]['id'],
            'Sales invoice'=>'sales_bill_detail.php?id='.$showcaseSale, 'Sales print preview'=>'sales_bill_pdf.php?id='.$showcaseSale,
            'Part-paid purchase'=>'purchase_bill_detail.php?id='.$showcasePurchase,'Payment form'=>'add_payment.php?bill_id='.$showcasePurchase,
            'Purchase print preview'=>'purchase_bill_pdf.php?id='.$showcasePurchase,'Opening balance editor'=>'add_opening_balance.php?edit_id='.$records['purchase_bills'][36],
            'Inventory report'=>'inventory_report.php','Inventory print preview'=>'inventory_report_pdf.php',
        ]];
        $problems=demoVerify($conn,$manifest);
        if($problems) throw new RuntimeException(implode('; ',$problems));
        demoAssertRelations($conn,$manifest);
        demoSaveManifest($manifestPath,$manifest);
        $manifestWritten=true;
        $conn->commit();
    } catch(Throwable $error) {
        $conn->rollback();
        if($manifestWritten && is_file($manifestPath)) unlink($manifestPath);
        throw $error;
    }
    echo "Demo dataset committed successfully. Existing records are unchanged.\n";
    demoPrintSummary($manifest);
    echo "Private ownership manifest: $manifestPath\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
