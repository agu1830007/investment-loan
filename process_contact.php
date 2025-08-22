<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));

    if ($name && $email && $phone && $message) {
        // You can replace this with actual email sending or database logic
        $to = 'austinechinasa37@gmail.com';
        $subject = 'New Contact Form Submission';
        $body = "Name: $name\nEmail: $email\nPhone: $phone\nMessage: $message";
        $headers = "From: $email\r\nReply-To: $email";
        mail($to, $subject, $body, $headers);
        echo '<p style="color:green;">Thank you for contacting us! We will get back to you soon.</p>';
    } else {
        echo '<p style="color:red;">Please fill in all fields.</p>';
    }
}
?>
