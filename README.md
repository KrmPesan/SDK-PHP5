# SDK-PHP5

SDK PHP untuk PHP versi 5 (Tested 5.3)

## Cara Penggunaan

1. Copy file **client.php** ke aplikasi anda
2. Buat file **token.json** dengan format berikut

```json
{
  "refreshToken":"eyJjdHkiOiJKV1QiLCJlbmMiOiJBMjU2xxxxxx",
  "deviceId":"ap-southeast-1_xxxxx-xxxxx-xxxx-xxxx-xxxxxxxxx",
}
```

3. Inisialisasi Instance dengan kode berikut

```php
<?php

require_once __DIR__ . "./client.php"; // lokasi file client.php di aplikasi anda, silahkan di sesuaikan dengan benar

$wa = new Client(array(
  "tokenFile" => __DIR__ // lokasi file token.json, cukup masukkan direktorinya dari file token.json nya saja, token akan automatis di regenerate dalam 24 jam
));

$send = $wa->sendMessageTemplateText(
  "081200000000", // Nomor Whatsapp Anda
  "template-code", // Code Template
  "id", // Bahasa Template
  array("John Doe") // Template Parameter, silahkan di sesuaikan dengan template anda
);

print($send);
```