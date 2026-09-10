<?php
require 'vendor/autoload.php';
require 'config.php';

// Increase execution time to prevent timeout during SMTP communication
set_time_limit(120);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database configuration
$servername = "localhost";
$username = "root"; // Change this to your DB username
$password = "";     // Change this to your DB password
$dbname = "simple-forms";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize input from Step 1
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);

    // Prevent double submissions: Check if this email was submitted in the last 60 seconds
    $check_sql = "SELECT id FROM submissions WHERE email = '$email' AND submitted_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
    $result = $conn->query($check_sql);
    if ($result && $result->num_rows > 0) {
        die("Error: You have already submitted the form recently. Please wait a minute before trying again.");
    }

    $address = $conn->real_escape_string($_POST['address']);
    $birthday = $conn->real_escape_string($_POST['birthday']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $sim_card = $conn->real_escape_string($_POST['sim_card']);

    // Collect and sanitize input from Step 2
    $fav_food = $conn->real_escape_string($_POST['fav_food']);
    $fav_drink = $conn->real_escape_string($_POST['fav_drink']);
    $hobby = $conn->real_escape_string($_POST['hobby']);
    $fav_color = $conn->real_escape_string($_POST['fav_color']);

    // Collect and sanitize input from Step 3
    $fun_question = $conn->real_escape_string($_POST['fun_question']);
    $fav_animal = $conn->real_escape_string($_POST['fav_animal']);
    $weird_food = $conn->real_escape_string($_POST['weird_food']);

    // Collect and sanitize input from Step 4
    $biggest_dream = $conn->real_escape_string($_POST['biggest_dream']);
    $role_model = $conn->real_escape_string($_POST['role_model']);
    $future_self = $conn->real_escape_string($_POST['future_self']);
    $world_change = $conn->real_escape_string($_POST['world_change']);

    // Handle Selfie Upload (Base64 from Camera)
    $selfie_path = "";
    if (isset($_POST['selfie_base64']) && !empty($_POST['selfie_base64'])) {
        $img_data = $_POST['selfie_base64'];

        // Remove the "data:image/jpeg;base64," part
        if (preg_match('/^data:image\/(\w+);base64,/', $img_data, $type)) {
            $img_data = substr($img_data, strpos($img_data, ',') + 1);
            $extension = strtolower($type[1]); // jpg, png, etc.
        } else {
            $extension = 'jpg';
        }

        $img_data = base64_decode($img_data);

        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = 'selfie_' . time() . '_' . uniqid() . '.' . $extension;
        $target_path = $upload_dir . $file_name;

        if (file_put_contents($target_path, $img_data)) {
            $selfie_path = $conn->real_escape_string($target_path);
        }
    }

    // SQL to insert data
    $sql = "INSERT INTO submissions (name, email, address, birthday, phone, sim_card, fav_food, fav_drink, hobby, fav_color, fun_question, fav_animal, weird_food, biggest_dream, role_model, future_self, world_change, selfie)
            VALUES ('$name', '$email', '$address', '$birthday', '$phone', '$sim_card', '$fav_food', '$fav_drink', '$hobby', '$fav_color', '$fun_question', '$fav_animal', '$weird_food', '$biggest_dream', '$role_model', '$future_self', '$world_change', '$selfie_path')";

    if ($conn->query($sql) === TRUE) {
        $submission_id = $conn->insert_id;

        // Handle Mood Photo Uploads
        $moods_data = [];
        $moods = [
            'happy' => 'mood_photo_happy',
            'sad' => 'mood_photo_sad',
            'excited' => 'mood_photo_excited',
            'angry' => 'mood_photo_angry'
        ];

        foreach ($moods as $mood => $file_field) {
            if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] === UPLOAD_ERR_OK) {
                $reason_field = 'mood_reason_' . $mood;
                $reason = isset($_POST[$reason_field]) ? $conn->real_escape_string($_POST[$reason_field]) : '';

                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $extension = pathinfo($_FILES[$file_field]['name'], PATHINFO_EXTENSION);
                $file_name = 'mood_' . $mood . '_' . time() . '_' . uniqid() . '.' . $extension;
                $target_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES[$file_field]['tmp_name'], $target_path)) {
                    $escaped_path = $conn->real_escape_string($target_path);
                    $mood_sql = "INSERT INTO mood_photos (submission_id, mood, photo_path, reason)
                                 VALUES ('$submission_id', '$mood', '$escaped_path', '$reason')";
                    $conn->query($mood_sql);
                    $moods_data[$mood] = ['path' => $target_path, 'reason' => $_POST[$reason_field]];
                }
            }
        }

        // --- EMAIL NOTIFICATION SECTION ---
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            // Recipients
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($email, $name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Your Journey Begins, " . $name . "!";

            // Build Moods Section for Email
            $moods_html = "";
            if (!empty($moods_data)) {
                $moods_html = "<div style='background-color: #fff7ed; border-radius: 16px; padding: 20px; margin: 20px 0; border: 1px solid #ffedd5;'>
                                <h3 style='color: #ea580c; margin-top: 0; font-size: 18px; border-bottom: 2px solid #fed7aa; padding-bottom: 10px;'>🎭 Your Emotional Moods</h3>
                                <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>";
                foreach ($moods_data as $mood => $data) {
                    $cid = "mood_" . $mood;
                    $moods_html .= "<tr style='border-bottom: 1px solid #ffedd5;'>
                                        <td style='padding: 12px 0; width: 60px; vertical-align: top;'>
                                            <img src='cid:$cid' style='width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 2px solid #fb923c;'>
                                        </td>
                                        <td style='padding: 12px 0; text-align: left;'>
                                            <b style='text-transform: capitalize; color: #c2410c;'>" . $mood . ":</b><br>
                                            <span style='color: #64748b;'>" . htmlspecialchars($data['reason']) . "</span>
                                        </td>
                                    </tr>";
                    $mail->addEmbeddedImage($data['path'], $cid);
                }
                $moods_html .= "</table></div>";
            }

            $mail->Body    = "
                <div style='background-color: #f1f5f9; padding: 20px; font-family: \"Inter\", Helvetica, Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
                        <div style='background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); padding: 40px 30px; text-align: center; color: white;'>
                            <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>Your Journey Begins, " . $name . "! ✨</h1>
                            <p style='margin: 10px 0 0; opacity: 0.9; font-size: 16px;'>Thank you for trusting our form with your story.</p>
                        </div>
                        <div style='padding: 30px; background-color: #ffffff;'>
                            <p style='color: #64748b; font-size: 16px; line-height: 1.6; text-align: center;'>Hello " . $name . ", we've successfully captured your profile. Here is a summary of everything you shared with us:</p>

                            <div style='background-color: #f8fafc; border-radius: 16px; padding: 20px; margin: 20px 0; border: 1px solid #e2e8f0;'>
                                <h3 style='color: #6366f1; margin-top: 0; font-size: 18px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;'>📝 Personal Details</h3>
                                <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                                    <tr><td style='padding: 8px 0; color: #64748b;'><b>Email:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $email . "</td></tr>
                                    <tr><td style='padding: 8px 0; color: #64748b;'><b>Address:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $address . "</td></tr>
                                    <tr><td style='padding: 8px 0; color: #64748b;'><b>Birthday:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $birthday . "</td></tr>
                                    <tr><td style='padding: 8px 0; color: #64748b;'><b>Phone:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $phone . "</td></tr>
                                </table>
                            </div>

                            <div style='background-color: #fdf2ff; border-radius: 16px; padding: 20px; margin: 20px 0; border: 1px solid #f0d1ff;'>
                                <h3 style='color: #a855f7; margin-top: 0; font-size: 18px; border-bottom: 2px solid #f0d1ff; padding-bottom: 10px;'>🌈 Favorites & Fun</h3>
                                <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                                    <tr><td style='padding: 8px 0; color: #64748b;'><b>Food/Drink:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $fav_food . " & " . $fav_drink . "</td></tr>
                                    <tr><td style='padding: 8px 0; color: #64748b;'><b>Hobby:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $hobby . "</td></tr>
                                    <tr style='border-top: 1px solid #f0d1ff;'><td style='padding: 8px 0; color: #64748b;'><b>Superpower:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $fun_question . "</td></tr>
                                    <tr><td style='padding: 8px 0; color: #64748b;'><b>Animal:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $fav_animal . "</td></tr>
                                </table>
                            </div>

                            <div style='background-color: #f0f9ff; border-radius: 16px; padding: 20px; margin: 20px 0; border: 1px solid #bae6fd;'>
                                <h3 style='color: #0ea5e9; margin-top: 0; font-size: 18px; border-bottom: 2px solid #bae6fd; padding-bottom: 10px;'>🌟 Life & Aspirations</h3>
                                <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                                    <tr><td style='padding: 8px 0; color: #64748b;'><b>Biggest Dream:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $biggest_dream . "</td></tr>
                                    <tr><td style='padding: 8px 0; color: #64748b;'><b>Role Model:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $role_model . "</td></tr>
                                    <tr><td style='padding: 8px 0; color: #64748b;'><b>Future (10yrs):</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $future_self . "</td></tr>
                                    <tr style='border-top: 1px solid #bae6fd;'><td style='padding: 8px 0; color: #64748b;'><b>World Change:</b></td><td style='padding: 8px 0; text-align: right; color: #1e293b;'>" . $world_change . "</td></tr>
                                </table>
                            </div>

                            " . $moods_html . "

                            <div style='text-align: center; margin-top: 30px;'>
                                <p style='color: #64748b; font-size: 14px;'>Your profile selfie:</p>
                                <img src='cid:user_selfie' style='width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 5px solid #6366f1; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>
                            </div>

                            <p style='text-align: center; margin-top: 30px; color: #64748b; font-size: 14px; font-style: italic;'>\"Believe in yourself and all that you are.\"</p>
                        </div>
                        <div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'>
                            <p style='margin: 0; color: #94a3b8; font-size: 12px;'>Best regards,<br><b style='color: #6366f1;'>Simple Form Team</b></p>
                        </div>
                    </div>
                </div>";

            if ($selfie_path && file_exists($selfie_path)) {
                $mail->addEmbeddedImage($selfie_path, 'user_selfie');
            }

            if($mail->send()) {
                file_put_contents("mail_log.txt", "[" . date("Y-m-d H:i:s") . "] SUCCESS: Email sent to $email\n", FILE_APPEND);
            }
        } catch (Exception $e) {
            // Log EXACT error to a file so we can diagnose why Gmail is failing
            $error_msg = "[" . date("Y-m-d H:i:s") . "] Email failed to " . $email . " | Error: " . $mail->ErrorInfo . "\n";
            file_put_contents("mail_errors.log", $error_msg, FILE_APPEND);
        }

        // Keep logging to file for local debugging
        $log_entry = "[" . date("Y-m-d H:i:s") . "] To: $email | Subject: " . $mail->Subject . "\n";
        file_put_contents("mail_log.txt", $log_entry, FILE_APPEND);
        // ----------------------------------

        // BEAUTIFUL SUCCESS PAGE
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Submission Successful</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
            <style>
                body {
                    font-family: "Inter", sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                    padding: 20px;
                }
                .success-card {
                    background: white;
                    padding: 3rem;
                    border-radius: 24px;
                    text-align: center;
                    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
                    max-width: 450px;
                    animation: popUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                }
                @keyframes popUp {
                    from { opacity: 0; transform: scale(0.8); }
                    to { opacity: 1; transform: scale(1); }
                }
                .icon-circle {
                    width: 80px;
                    height: 80px;
                    background: #dcfce7;
                    color: #22c55e;
                    border-radius: 50%;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    font-size: 40px;
                    margin: 0 auto 1.5rem;
                }
                h1 { color: #1e293b; margin-bottom: 1rem; font-size: 1.75rem; }
                p { color: #64748b; line-height: 1.6; margin-bottom: 2rem; }
                .btn-back {
                    background: #6366f1;
                    color: white;
                    padding: 0.8rem 2rem;
                    text-decoration: none;
                    border-radius: 12px;
                    font-weight: 600;
                    transition: all 0.2s;
                }
                .btn-back:hover {
                    background: #4f46e5;
                    transform: translateY(-2px);
                }
            </style>
        </head>
        <body>
            <div class="success-card">
                <div class="icon-circle">✓</div>
                <h1>You Did It!</h1>
                <p>Your full profile is now complete. We have sent a special thank you email to <b>' . $email . '</b>.</p>
                <a href="index.html" class="btn-back">Return to Home</a>
            </div>
        </body>
        </html>';
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
?>
