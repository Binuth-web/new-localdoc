<?php
require 'api/db_connect.php';

// Small-scale medical centers replacing existing ones.
// IDs 1-5 kept same (linked to sessions/users), 6-10 also updated in-place.
$centers = [
    // id, name, type, area, address, phone, hours
    [1, 'Suwasetha Family Clinic',       'General Practice',        'Kandy City',        '12/A, Peradeniya Rd, Kandy',            '081-223-4455', 'Mon–Sat 8am–6pm'],
    [2, 'Amaya Community Clinic',         'Community Health Clinic', 'William Gopallawa', '34, Temple St, Kandy',                  '081-234-5678', 'Mon–Fri 8am–5pm'],
    [3, 'Isuru Medical Consultations',    'General Practice',        'Katugastota',       '78, Katugastota Rd, Kandy',             '081-345-6789', 'Mon–Sat 8am–6pm'],
    [4, 'Rosebud Clinic & Pharmacy',      'Clinic & Pharmacy',       'Peradeniya Road',   '45, Peradeniya Rd, Kandy',              '081-456-7890', 'Daily 7am–9pm'],
    [5, 'Sunrise GP Clinic',              'General Practice',        'Asgiriya',          '22, Rajapihilla Mawatha, Kandy',        '081-567-8901', 'Mon–Sat 7:30am–5pm'],
    [6, 'Wellness Family Clinic',         'Family Clinic',           'Getambe',           '11, Station Rd, Getambe, Kandy',        '081-678-9012', 'Mon–Fri 8am–6pm'],
    [7, 'MediCare Walk-In Clinic',        'Walk-In Clinic',          'Kandy City',        '56, DS Senanayake Veediya, Kandy',      '081-789-0123', 'Daily 8am–8pm'],
    [8, 'Green Cross GP Surgery',         'General Practice',        'Dharmaraja',        '3, Rajapihilla Rd, Kandy',              '081-890-1234', 'Mon–Sat 9am–6pm'],
    [9, 'Lakeside Community Clinic',      'Community Health Clinic', 'Kandy Lake',        '18, Lake Rd, Kandy',                    '081-901-2345', 'Mon–Fri 8am–5pm'],
    [10,'Kandy Neighbourhood Clinic',     'Neighbourhood Clinic',    'Ampitiya',          '5, Ampitiya Rd, Kandy',                 '081-012-3456', 'Mon–Sat 8am–7pm'],
];

$sql = "UPDATE medical_centers SET name=?, type=?, area=?, address=?, phone=?, hours=?, available=1, online_booking_enabled=1 WHERE id=?";
$stmt = $pdo->prepare($sql);

foreach ($centers as $c) {
    [$id, $name, $type, $area, $address, $phone, $hours] = $c;
    $stmt->execute([$name, $type, $area, $address, $phone, $hours, $id]);
    echo "Updated center ID $id → $name\n";
}

echo "\nDone! All centers updated to small-scale.\n";
?>
