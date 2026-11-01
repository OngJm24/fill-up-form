<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

function make_mailer(): PHPMailer
{
    $smtp = config('smtp');
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtp['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp['user'];
    $mail->Password   = $smtp['pass'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)$smtp['port'];
    $mail->Timeout    = 20;
    $mail->CharSet    = PHPMailer::CHARSET_UTF8;
    $mail->setFrom($smtp['from'], $smtp['from_name']);
    return $mail;
}

/**
 * Sends the thank-you email. Every user value is HTML-escaped before it goes into the body.
 * A mail failure is logged but never breaks the submission itself.
 */
function send_confirmation_email(int $id, array $v, ?string $selfie, array $moods): bool
{
    $row = fn(string $label, string $value) =>
        "<tr><td style='padding:8px 0;color:#64748b;vertical-align:top;width:40%'><b>" . e($label) . "</b></td>"
        . "<td style='padding:8px 0;color:#0f172a;text-align:right'>" . nl2br(e($value)) . "</td></tr>";
    $section = fn(string $title, string $color, string $rows) =>
        "<div style='border:1px solid #e2e8f0;border-radius:16px;padding:20px;margin:18px 0'>"
        . "<h3 style='margin:0 0 8px;color:$color;font-size:16px'>" . $title . "</h3>"
        . "<table style='width:100%;border-collapse:collapse;font-size:14px'>$rows</table></div>";

    try {
        $mail = make_mailer();
        $mail->addAddress($v['email'], $v['name']);
        $mail->isHTML(true);
        $mail->Subject = 'Your journey begins, ' . $v['name'] . '!';

        $moodHtml = '';
        foreach ($moods as $mood => $m) {
            $cid = 'mood_' . $mood;
            $mail->addEmbeddedImage(UPLOAD_DIR . '/' . $m['file'], $cid);
            $moodHtml .= "<tr><td style='padding:10px 0;width:64px'><img src='cid:$cid' width='52' height='52' style='border-radius:10px;object-fit:cover'></td>"
                . "<td style='padding:10px 0;font-size:14px'><b>" . MOODS[$mood]['emoji'] . ' ' . e(MOODS[$mood]['label']) . "</b><br>"
                . "<span style='color:#64748b'>" . nl2br(e($m['reason'] ?: '—')) . "</span></td></tr>";
        }

        $selfieHtml = '';
        if ($selfie) {
            $mail->addEmbeddedImage(UPLOAD_DIR . '/' . $selfie, 'user_selfie');
            $selfieHtml = "<div style='text-align:center;margin:8px 0 4px'><img src='cid:user_selfie' width='120' height='120' "
                . "style='border-radius:50%;object-fit:cover;border:4px solid #fff;box-shadow:0 6px 20px rgba(79,70,229,.35)'></div>";
        }

        $name = e($v['name']);
        $mail->Body = "<div style='background:#f1f5f9;padding:24px;font-family:Helvetica,Arial,sans-serif'>"
            . "<div style='max-width:600px;margin:0 auto;background:#fff;border-radius:24px;overflow:hidden;border:1px solid #e2e8f0'>"
            . "<div style='background:linear-gradient(135deg,#4f46e5,#c026d3);padding:36px 28px;text-align:center;color:#fff'>"
            . "<h1 style='margin:0;font-size:26px'>Your journey begins, $name! ✨</h1>"
            . "<p style='margin:8px 0 0;opacity:.9'>Thank you for sharing your story with us.</p></div>"
            . "<div style='padding:28px'>$selfieHtml"
            . $section('📝 Personal details', '#4f46e5',
                $row('Email', $v['email']) . $row('Address', $v['address']) . $row('Birthday', $v['birthday'])
                . $row('Mobile', $v['phone']) . $row('SIM network', $v['sim_card']))
            . $section('🌈 Favorites & fun', '#c026d3',
                $row('Food & drink', $v['fav_food'] . ' & ' . $v['fav_drink']) . $row('Hobby', $v['hobby'])
                . $row('Color', $v['fav_color']) . $row('Superpower', $v['fun_question'])
                . $row('Spirit animal', $v['fav_animal']) . $row('Weird combo', $v['weird_food']))
            . $section('🌟 Life & aspirations', '#0284c7',
                $row('Biggest dream', $v['biggest_dream']) . $row('Role model', $v['role_model'])
                . $row('In 10 years', $v['future_self']) . $row('World change', $v['world_change']))
            . ($moodHtml ? $section('🎭 Your moods', '#ea580c', $moodHtml) : '')
            . "<p style='text-align:center;color:#64748b;font-style:italic;margin-top:24px'>“Believe in yourself and all that you are.”</p>"
            . "</div><div style='background:#f8fafc;padding:18px;text-align:center;color:#94a3b8;font-size:12px'>"
            . e(config('smtp')['from_name']) . "</div></div></div>";
        $mail->AltBody = "Hi {$v['name']}, thank you! Your profile was saved successfully.";

        $mail->send();
        app_log('mail.log', "OK submission #$id");
        return true;
    } catch (MailException $ex) {
        app_log('mail.log', "FAILED submission #$id: " . ($mail->ErrorInfo ?? $ex->getMessage()));
        return false;
    }
}
