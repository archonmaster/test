<?php
$room = collection("Номерной фонд")->findOne(["_id" => $room])["name"];
$days_to_pay = cockpit('regions:region_field', 'Настройки', 'days_to_pay', 'value');
$prepay = cockpit('regions:region_field', 'Настройки', 'prepay', 'value');
$prepay_summ = $summ * $prepay;
$prepay = $prepay * 100;
$pay = $summ - $paid;
$start_time = cockpit('regions:region_field', 'Настройки', 'start_time', 'value');
$long = ceil((strtotime($date_end) - strtotime($date_start))/(60*60*24));
$date = date("d.m.Y", strtotime($date));
$date_start = date("d.m.Y", strtotime($date_start));
$date_end = date("d.m.Y", strtotime($date_end));
$user = collection("Клиенты")->findOne(["_id" => $client]);
$client = implode(" ", [$user["surname"], $user["name"], $user["second_name"]]);
$client_name = $user["name"];
$email = $user["email"];
$phone = $user["phone"];

$templates = [
    "Ваша бронь аннулирована" => "nulled",
    "Ваша бронь принята в обработку" => "before",
  	"Ваша бронь на рассмотрении" => "process",
    "Ваша бронь одобрена" => "approved",
    "Ваша бронь оплачена" => "paid"
];
$template = $templates[$__mailsubject];
$template = cockpit('regions:region_field', 'Шаблоны писем', $template, 'value');

$message = preg_replace('/\{\$([A-Za-z_]+)\}/e', "$$1", $template);
$message = cockpit("cockpit")->markdown($message, $extra = false);

$hotelname_genetive = cockpit('regions:region_field', 'Настройки', 'hotelname_genetive', 'value');
$hotel_phones = cockpit('regions:region_field', 'Контакты', 'phones', 'value');
$hotel_phone = $hotel_phones[0];
$sign = cockpit('regions:region_field', 'Шаблоны писем', 'sign', 'value');
$sign = preg_replace('/\{\$([A-Za-z_]+)\}/e', "$$1", $sign);
$sign = cockpit("cockpit")->markdown($sign, $extra = false);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<body>
<?php echo $message; ?>
<p></p>
<?php echo $sign; ?>
</body>
</html>