<?php
// Database connection
$conn = new mysqli('173.252.167.30', 'technol4_health_user', 'health_user', 'technol4_maternal_health');

// Get mother ID from query parameter
$mother_id = intval($_GET['id']);

// Fetch mother details
$stmt = $conn->prepare("
    SELECT * FROM pregnant_women 
    WHERE id = ?
");
$stmt->bind_param("i", $mother_id);
$stmt->execute();
$mother = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch appointments
$stmt = $conn->prepare("
    SELECT * FROM appointments 
    WHERE mother_id = ? 
    ORDER BY appointment_date ASC
");
$stmt->bind_param("i", $mother_id);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch vaccines
$stmt = $conn->prepare("
    SELECT * FROM vaccination_schedule 
    WHERE mother_id = ? 
    ORDER BY scheduled_date ASC
");
$stmt->bind_param("i", $mother_id);
$stmt->execute();
$vaccines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<!-- Mother Profile Content -->
<div class="container" data-id="<?= $mother['id'] ?>">
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-primary">Personal Information</h5>
                    <p><strong>Name:</strong> <?= htmlspecialchars($mother['full_name']) ?></p>
                    <p><strong>Age:</strong> <?= htmlspecialchars($mother['age']) ?></p>
                    <p><strong>National ID:</strong> <?= htmlspecialchars($mother['national_id']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($mother['contact_number']) ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-primary">Emergency Information</h5>
                    <p><strong>Contact Name:</strong> <?= htmlspecialchars($mother['emergency_contact_name']) ?></p>
                    <p><strong>Contact Number:</strong> <?= htmlspecialchars($mother['emergency_contact_number']) ?></p>
                    <p><strong>Residence:</strong> <?= htmlspecialchars($mother['residence']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-primary">Medical History</h5>
                    <p><strong>HIV Status:</strong> <?= htmlspecialchars($mother['hiv_status']) ?></p>
                    <p><strong>Chronic Illnesses:</strong> <?= htmlspecialchars($mother['chronic_illnesses']) ?></p>
                    <p><strong>Past Complications:</strong> <?= htmlspecialchars($mother['past_complications']) ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-primary">Current Status</h5>
                    <p><strong>Expected Birth Date:</strong> <?= htmlspecialchars($mother['expected_birth_date']) ?></p>
                    <p><strong>Transport Access:</strong> <?= htmlspecialchars($mother['transport_access']) ?></p>
                    <p><strong>Assigned Clinic:</strong> <?= htmlspecialchars($mother['clinic']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointments -->
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title text-primary mb-3">Appointments</h5>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Clinic</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['appointment_date']) ?></td>
                            <td><?= ucfirst(htmlspecialchars($a['status'])) ?></td>
                            <td><?= htmlspecialchars($mother['clinic']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Vaccines -->
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title text-primary mb-3">Vaccination Schedule</h5>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Vaccine</th>
                        <th>Scheduled Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vaccines as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['vaccine_type']) ?></td>
                            <td><?= htmlspecialchars($v['scheduled_date']) ?></td>
                            <td><?= ucfirst(htmlspecialchars($v['status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>