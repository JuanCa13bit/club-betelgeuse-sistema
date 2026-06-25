<?php
$api_key = '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';

$data = [
    'sender' => ['email' => 'ClubBetelgeuse2017@gmail.com', 'name' => 'Club Betelgeuse'],
    'to' => [['email' => 'clujanmeza@gmail.com', 'name' => 'Admin']],
    'subject' => 'Prueba directa ' . date('H:i:s'),
    'htmlContent' => '<h1>¿Llegó?</h1><p>Correo enviado a las ' . date('H:i:s') . '</p>'
];

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'api-key: ' . $api_key,
    'content-type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

if (isset($result['messageId'])) {
    echo '✅ Enviado a las ' . date('H:i:s') . '. Revisa Gmail (puede tardar 2-3 minutos).';
} else {
    echo '❌ Error: ' . print_r($result, true);
}
?>