<?php
// Start session and include PHPMailer files
session_start();
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/POP3.php';
//require_once 'PHPMailer/src/OAuth.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\POP3;

// Load configuration (replace with your actual credentials)
$gmail_user = 'festival.videos.matematica@gmail.com';
$gmail_pass = 'esmc jelp jnof trmp'; // senha de app gerada pelo Google
$gmail_host = 'imap.gmail.com';

// Function to fetch emails using IMAP
function fetchEmails($username, $password, $host = 'imap.gmail.com') {
    $emails = [];
    
    // Connect to IMAP server
    $inbox = imap_open("{imap.gmail.com:993/imap/ssl}INBOX", $username, $password);
    if (!$inbox) {
        die('Cannot connect to Gmail: ' . imap_last_error());
    }
    
    // Get all emails (sorted newest first)
    $emails_ids = imap_search($inbox, 'ALL', SE_UID);
    
    if ($emails_ids) {
        rsort($emails_ids); // Sort newest first
        
        foreach ($emails_ids as $email_uid) {
            $overview = imap_fetch_overview($inbox, $email_uid, FT_UID);
            $structure = imap_fetchstructure($inbox, $email_uid, FT_UID);
            $message = imap_fetchbody($inbox, $email_uid, '1', FT_UID);
            
            $email = [
                'uid' => $email_uid,
                'subject' => isset($overview[0]->subject) ? $overview[0]->subject : '(no subject)',
                'from' => isset($overview[0]->from) ? $overview[0]->from : 'Unknown',
                'date' => isset($overview[0]->date) ? $overview[0]->date : '',
                'seen' => isset($overview[0]->seen) ? $overview[0]->seen : false,
                'message' => quoted_printable_decode($message)
            ];
            
            $emails[] = $email;
            
            if (count($emails) >= 10) break; // Limit to 10 emails
        }
    }
    
    imap_close($inbox);
    return $emails;
}

// Fetch emails if credentials are set
$emails = [];
if (!empty($gmail_user) && !empty($gmail_pass)) {
    $emails = fetchEmails($gmail_user, $gmail_pass, $gmail_host);
}

// Check if we're requesting a specific email
if (isset($_GET['email_uid'])) {
    $email_uid = (int)$_GET['email_uid'];
    $selected_email = null;
    
    foreach ($emails as $email) {
        if ($email['uid'] === $email_uid) {
            $selected_email = $email;
            break;
        }
    }
    
    if ($selected_email) {
        // Return JSON for AJAX request
        header('Content-Type: application/json');
        echo json_encode($selected_email);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizador de E-mails</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .email-list {
            cursor: pointer;
        }
        .email-list:hover {
            background-color: #f8f9fa;
        }
        .unread {
            font-weight: bold;
        }
        #emailModal .modal-body {
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Meus E-mails</h1>
        
        <?php if (empty($emails)): ?>
            <div class="alert alert-info">Nenhum e-mail encontrado ou não foi possível conectar à conta.</div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($emails as $email): ?>
                    <a href="#" class="list-group-item list-group-item-action email-list <?= !$email['seen'] ? 'unread' : '' ?>" 
                       data-email-uid="<?= $email['uid'] ?>" data-bs-toggle="modal" data-bs-target="#emailModal">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1"><?= htmlspecialchars($email['subject']) ?></h5>
                            <small><?= htmlspecialchars($email['date']) ?></small>
                        </div>
                        <p class="mb-1">De: <?= htmlspecialchars($email['from']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Email Modal -->
    <div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="emailModalLabel">Carregando...</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Carregando conteúdo do e-mail...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('.email-list').click(function(e) {
                e.preventDefault();
                var emailUid = $(this).data('email-uid');
                
                $.get(window.location.href, { email_uid: emailUid }, function(data) {
                    $('#emailModalLabel').text(data.subject);
                    $('.modal-body').html(
                        '<p><strong>De:</strong> ' + data.from + '</p>' +
                        '<p><strong>Data:</strong> ' + data.date + '</p>' +
                        '<hr>' +
                        '<div>' + data.message + '</div>'
                    );
                }, 'json');
            });
        });
    </script>
</body>
</html>