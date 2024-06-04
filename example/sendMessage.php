<?php

require_once __DIR__ . "/../client.php";

$wa = new Client(array(
  "tokenFile" => __DIR__
));

$send = $wa->sendMessageTemplateText(
  "081216667996",
  "device_offline",
  "id",
  array("WGP001", "Disconnect")
);

print($send);