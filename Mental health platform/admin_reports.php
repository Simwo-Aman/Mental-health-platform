<?php
// Include the admin authentication file
include 'admin_auth.php';

// Check if FPDF is available
if (!file_exists('fpdf/fpdf.php')) {
    $fpdf_error = true;
} else {
    require('fpdf/fpdf.php');
    $fpdf_error = false;
}

// Class for Professionals Report PDF
class ProfessionalsReportPDF extends FPDF {
    function Header() {
        // Logo (if you have one)
        // $this->Image('logo.png', 10, 6, 30);
        
        // Title
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'Mental Health Platform - Professional Activity Report', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 10, 'Generated on: ' . date('F j, Y'), 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function ProfessionalSummaryTable($header, $data) {
        // Colors, line width and bold font
        $this->SetFillColor(41, 128, 185); // #2980b9 blue
        $this->SetTextColor(255);
        $this->SetDrawColor(128, 128, 128);
        $this->SetLineWidth(.3);
        $this->SetFont('', 'B');
        
        // Header
        $w = array(60, 40, 40, 50);
        for($i=0; $i<count($header); $i++)
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        $this->Ln();
        
        // Color and font restoration
        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
        $this->SetFont('');
        
        // Data
        $fill = false;
        foreach($data as $row) {
            $this->Cell($w[0], 6, $row[0], 'LR', 0, 'L', $fill);
            $this->Cell($w[1], 6, $row[1], 'LR', 0, 'C', $fill);
            $this->Cell($w[2], 6, $row[2], 'LR', 0, 'C', $fill);
            $this->Cell($w[3], 6, $row[3], 'LR', 0, 'C', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

// Check if a report is requested
$report_type = isset($_GET['report']) ? $_GET['report'] : '';
$generated_report = false;

if (!$fpdf_error && !empty($report_type)) {
    
    switch ($report_type) {
        case 'professionals_activity':
            // Generate PDF report for professional activity
            
            // Get professional data
            $query = "SELECT u.fullname, 
                     (SELECT COUNT(*) FROM professional_clients WHERE professional_id = u.id) as client_count,
                     (SELECT COUNT(*) FROM appointments WHERE professional_id = u.id) as appointment_count,
                     (SELECT COUNT(*) FROM mental_health_resources WHERE professional_id = u.id) as resource_count
                     FROM users u 
                     WHERE u.role = 'professional'
                     ORDER BY client_count DESC";
            
            $result = $conn->query($query);
            
            if ($result) {
                // Create PDF
                $pdf = new ProfessionalsReportPDF();
                $pdf->AliasNbPages();
                $pdf->AddPage();
                
                // Introduction text
                $pdf->SetFont('Arial', '', 11);
                $pdf->MultiCell(0, 10, 'This report summarizes the activity of all mental health professionals on the platform, showing the number of clients, appointments, and resources created by each professional.', 0, 'L');
                $pdf->Ln(5);
                
                // Add professional data to table
                $header = array('Professional Name', 'Clients', 'Appointments', 'Resources');
                $data = array();
                
                while($row = $result->fetch_assoc()) {
                    $data[] = array(
                        $row['fullname'],
                        $row['client_count'],
                        $row['appointment_count'],
                        $row['resource_count']
                    );
                }
                
                $pdf->ProfessionalSummaryTable($header, $data);
                
                // Output PDF
                $pdf->Output('D', 'Professionals_Activity_Report.pdf');
                exit;
            }
            break;
            
        case 'platform_summary':
            // Generate PDF report for platform summary
            
            // Get summary data
            $users_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
            $professionals_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'professional'")->fetch_assoc()['count'];
            $verified_professionals = $conn->query("SELECT COUNT(*) as count FROM professional_profiles WHERE verified = 1")->fetch_assoc()['count'];
            $appointments_count = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
            $completed_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'")->fetch_assoc()['count'];
            $resources_count = $conn->query("SELECT COUNT(*) as count FROM mental_health_resources")->fetch_assoc()['count'];
            $published_resources = $conn->query("SELECT COUNT(*) as count FROM mental_health_resources WHERE is_published = 1")->fetch_assoc()['count'];
            $messages_count = $conn->query("SELECT COUNT(*) as count FROM chat_messages")->fetch_assoc()['count'];
            
            // Create PDF
            $pdf = new FPDF();
            $pdf->AliasNbPages();
            $pdf->AddPage();
            
            // Title
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, 'Mental Health Platform - Summary Report', 0, 1, 'C');
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y'), 0, 1, 'C');
            $pdf->Ln(10);
            
            // Introduction
            $pdf->SetFont('Arial', '', 11);
            $pdf->MultiCell(0, 10, 'This report provides an overview of the platform\'s current status and activity levels.', 0, 'L');
            $pdf->Ln(5);
            
            // Statistics
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Platform Statistics', 0, 1, 'L');
            
            $pdf->SetFont('Arial', '', 11);
            
            // Users section
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 10, 'Users:', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(60, 8, 'Regular Users:', 0, 0, 'L');
            $pdf->Cell(30, 8, $users_count, 0, 1, 'L');
            $pdf->Cell(60, 8, 'Professionals:', 0, 0, 'L');
            $pdf->Cell(30, 8, $professionals_count, 0, 1, 'L');
            $pdf->Cell(60, 8, 'Verified Professionals:', 0, 0, 'L');
            $pdf->Cell(30, 8, $verified_professionals, 0, 1, 'L');
            $pdf->Ln(5);
            
            // Activity section
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 10, 'Activity:', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(60, 8, 'Total Appointments:', 0, 0, 'L');
            $pdf->Cell(30, 8, $appointments_count, 0, 1, 'L');
            $pdf->Cell(60, 8, 'Completed Appointments:', 0, 0, 'L');
            $pdf->Cell(30, 8, $completed_appointments, 0, 1, 'L');
            $pdf->Cell(60, 8, 'Total Resources:', 0, 0, 'L');
            $pdf->Cell(30, 8, $resources_count, 0, 1, 'L');
            $pdf->Cell(60, 8, 'Published Resources:', 0, 0, 'L');
            $pdf->Cell(30, 8, $published_resources, 0, 1, 'L');
            $pdf->Cell(60, 8, 'Messages Exchanged:', 0, 0, 'L');
            $pdf->Cell(30, 8, $messages_count, 0, 1, 'L');
            
            // Output PDF
            $pdf->Output('D', 'Platform_Summary_Report.pdf');
            exit;
            break;
            
        case 'active_connections':
            // Generate PDF report for connections between professionals and clients
            
            // Get connections data
            $query = "SELECT u_prof.fullname as professional_name, u_client.fullname as client_name,
                     pc.status, pc.created_at as connection_date,
                     (SELECT COUNT(*) FROM appointments WHERE professional_id = pc.professional_id AND client_id = pc.client_id) as appointment_count
                     FROM professional_clients pc
                     JOIN users u_prof ON pc.professional_id = u_prof.id
                     JOIN users u_client ON pc.client_id = u_client.id
                     ORDER BY pc.status, u_prof.fullname";
            
            $result = $conn->query($query);
            
            if ($result) {
                // Create PDF
                $pdf = new FPDF();
                $pdf->AliasNbPages();
                $pdf->AddPage();
                
                // Title
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->Cell(0, 10, 'Mental Health Platform - Connections Report', 0, 1, 'C');
                $pdf->SetFont('Arial', 'I', 10);
                $pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y'), 0, 1, 'C');
                $pdf->Ln(10);
                
                // Introduction
                $pdf->SetFont('Arial', '', 11);
                $pdf->MultiCell(0, 10, 'This report details all connections between professionals and clients, including their status and appointment history.', 0, 'L');
                $pdf->Ln(5);
                
                // Table header
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetFillColor(41, 128, 185);
                $pdf->SetTextColor(255);
                $pdf->Cell(50, 8, 'Professional', 1, 0, 'C', true);
                $pdf->Cell(50, 8, 'Client', 1, 0, 'C', true);
                $pdf->Cell(30, 8, 'Status', 1, 0, 'C', true);
                $pdf->Cell(35, 8, 'Connection Date', 1, 0, 'C', true);
                $pdf->Cell(25, 8, 'Appointments', 1, 1, 'C', true);
                
                // Table data
                $pdf->SetFont('Arial', '', 10);
                $pdf->SetTextColor(0);
                $pdf->SetFillColor(224, 235, 255);
                
                $fill = false;
                while($row = $result->fetch_assoc()) {
                    $pdf->Cell(50, 7, $row['professional_name'], 1, 0, 'L', $fill);
                    $pdf->Cell(50, 7, $row['client_name'], 1, 0, 'L', $fill);
                    
                    $status = ucfirst($row['status']);
                    $pdf->Cell(30, 7, $status, 1, 0, 'C', $fill);
                    
                    $connection_date = date('M d, Y', strtotime($row['connection_date']));
                    $pdf->Cell(35, 7, $connection_date, 1, 0, 'C', $fill);
                    
                    $pdf->Cell(25, 7, $row['appointment_count'], 1, 1, 'C', $fill);
                    
                    $fill = !$fill;
                }
                
                // Output PDF
                $pdf->Output('D', 'Connections_Report.pdf');
                exit;
            }
            break;
    }
    
    $generated_report = true;
}

// Get statistics for the dashboard
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
$total_professionals = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'professional'")->fetch_assoc()['count'];
$total_resources = $conn->query("SELECT COUNT(*) as count FROM mental_health_resources")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .report-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            background-color: white;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .report-icon {
            font-size: 48px;
            color: #3498db;
            margin-bottom: 20px;
        }
        .report-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .report-description {
            color: #666;
            margin-bottom: 25px;
        }
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #3498db;
            margin: 10px 0;
        }
        .download-btn {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.3s ease;
        }
        .download-btn:hover {
            background-color: #2980b9;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-chart-bar" style="color: #3498db;"></i> Admin Reports</h1>
                <p>Generate and download reports about platform activity</p>
            </div>
            <a href="admin_dash.php" class="action-button secondary" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if($fpdf_error): ?>
        <div class="error-message">
            <h3><i class="fas fa-exclamation-triangle"></i> FPDF Library Missing</h3>
            <p>The FPDF library is required to generate PDF reports but was not found. Please install the FPDF library to enable PDF report generation.</p>
            <p>You can download FPDF from <a href="http://www.fpdf.org/" target="_blank">http://www.fpdf.org/</a> and place it in a folder named "fpdf" in your root directory.</p>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2><i class="fas fa-chart-line"></i> Platform Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div>Total Users</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-md" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number"><?php echo $total_professionals; ?></div>
                    <div>Professionals</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-check" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number"><?php echo $total_appointments; ?></div>
                    <div>Appointments</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-book-medical" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number"><?php echo $total_resources; ?></div>
                    <div>Resources</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-file-pdf"></i> Available Reports</h2>
            <p>Select a report to generate and download as PDF:</p>
            
            <div class="report-grid">
                <div class="report-card">
                    <div class="report-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="report-title">Professional Activity Report</div>
                    <div class="report-description">
                        Shows the number of clients, appointments, and resources for each professional.
                    </div>
                    <a href="admin_reports.php?report=professionals_activity" class="download-btn" <?php echo $fpdf_error ? 'disabled' : ''; ?>>
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                </div>
                
                <div class="report-card">
                    <div class="report-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="report-title">Platform Summary Report</div>
                    <div class="report-description">
                        Provides an overview of the platform's current status and activity levels.
                    </div>
                    <a href="admin_reports.php?report=platform_summary" class="download-btn" <?php echo $fpdf_error ? 'disabled' : ''; ?>>
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                </div>
                
                <div class="report-card">
                    <div class="report-icon">
                        <i class="fas fa-link"></i>
                    </div>
                    <div class="report-title">Client-Professional Connections</div>
                    <div class="report-description">
                        Details all connections between professionals and clients, including their status and appointment history.
                    </div>
                    <a href="admin_reports.php?report=active_connections" class="download-btn" <?php echo $fpdf_error ? 'disabled' : ''; ?>>
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                </div>
            </div>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>