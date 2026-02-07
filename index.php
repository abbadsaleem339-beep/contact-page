<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables or configuration
require_once 'config.php';
require_once 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Handle form submission
$response_message = '';
$response_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $subject = sanitize_input($_POST['subject'] ?? '');
    $message = sanitize_input($_POST['message'] ?? '');
    
    // Validate inputs
    $errors = validate_form($name, $email, $subject, $message);
    
    if (empty($errors)) {
        // Subscribe email to Mailchimp list
        $result = subscribe_to_mailchimp($email, $name);
        
        if ($result['success']) {
            // Send email notification
            $email_sent = send_contact_email($name, $email, $subject, $message);
            
            if ($email_sent) {
                $response_message = 'Thank you! Your message has been received. We will get back to you soon.';
                $response_type = 'success';
            } else {
                $response_message = 'Email could not be sent. Please try again.';
                $response_type = 'error';
            }
        } else {
            $response_message = $result['message'] ?? 'Subscription failed. Please try again.';
            $response_type = 'error';
        }
    } else {
        $response_message = implode('<br>', $errors);
        $response_type = 'error';
    }
}

/**
 * Sanitize user input
 */
function sanitize_input($input) {
    return trim(htmlspecialchars(stripslashes($input), ENT_QUOTES, 'UTF-8'));
}

/**
 * Validate form inputs
 */
function validate_form($name, $email, $subject, $message) {
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    
    if (empty($subject)) {
        $errors[] = 'Subject is required.';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required.';
    }
    
    return $errors;
}

/**
 * Subscribe email to Mailchimp list
 */
function subscribe_to_mailchimp($email, $name) {
    $api_key = MAILCHIMP_API_KEY;
    $list_id = MAILCHIMP_LIST_ID;
    $server = MAILCHIMP_SERVER;
    
    if (empty($api_key) || empty($list_id)) {
        return [
            'success' => false,
            'message' => 'Mailchimp configuration is missing.'
        ];
    }
    
    $url = "https://{
        $server}.api.mailchimp.com/3.0/lists/{$list_id}/members";
    
    $client = new Client();
    
    try {
        $response = $client->post($url, [
            'auth' => ['anystring', $api_key],
            'json' => [
                'email_address' => $email,
                'status' => 'subscribed',
                'merge_fields' => [
                    'FNAME' => explode(' ', $name)[0],
                    'LNAME' => count(explode(' ', $name)) > 1 ? end(explode(' ', $name)) : ''
                ]
            ]
        ]);
        
        return [
            'success' => true,
            'message' => 'Successfully subscribed to mailing list.'
        ];
    } catch (RequestException $e) {
        $status_code = $e->getResponse()->getStatusCode();
        
        // If email already exists, it's okay
        if ($status_code == 400) {
            $body = json_decode($e->getResponse()->getBody(), true);
            if (isset($body['detail']) && strpos($body['detail'], 'already') !== false) {
                return [
                    'success' => true,
                    'message' => 'Email already subscribed.'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Failed to subscribe: ' . $e->getMessage()
        ];
    }
}

/**
 * Send contact email notification
 */
function send_contact_email($name, $email, $subject, $message) {
    $to = ADMIN_EMAIL;
    $headers = "From: " . $email . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $email_body = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>New Contact Form Submission</h2>
        <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
        <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
        <hr>
        <p><strong>Message:</strong></p>
        <p>" . nl2br(htmlspecialchars($message)) . "</p>
    </body>
    </html>
    ";
    
    return mail($to, "New Contact: " . $subject, $email_body, $headers);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="email"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Get in Touch</h1>
        <p class="subtitle">We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
        
        <?php if (!empty($response_message)): ?>
            <div class="alert alert-<?php echo $response_type; ?>">
                <?php echo $response_message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="subject">Subject *</label>
                <input type="text" id="subject" name="subject" required>
            </div>
            
            <div class="form-group">
                <label for="message">Message *</label>
                <textarea id="message" name="message" required></textarea>
            </div>
            
            <button type="submit">Send Message</button>
        </form>
    </div>
</body>
</html>