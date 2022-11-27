# Gree AC PHP API
API to controll Gree AC with WiFi by network

To make it work you should initialize connection to Gree+ with getting secret-key witch generating every time when you reset WiFi on Gree AC.

1. Press Mode + WiFi button on remote of Gree AC (AC must beep once) and wait 2 minutes (AC will go to WiFi access point mode).
2. Search Wifi AP with name like: f4211ede6d31 and connect your PC to it (WiFi password is: 12345678)
3. Write simple script like finder.php: 
```php
<?php
  require_once 'vendor/autoload.php';

  $gree = new \Gree\GreeAC();
  $gree->setDebug(true); // If you want to see all
  
  print_r($gree->scan());
?>
```
4. Run from console "php finder.php" you will see response: 
```php
Array
(
    [t] => dev
    [cid] => f4211ede6d31
    [bc] => gree
    [brand] => gree
    [catalog] => gree
    [mac] => f4211ede6d31
    [mid] => 10002
    [model] => gree
    [name] => 1ede6d31
    [series] => gree
    [vender] => 1
    [ver] => V1.1.13
    [lock] => 0
)
```
5. Copy cid to somewhere
6. To get bind key (secure key) write script like bind.php:
```php
<?php
  require_once 'vendor/autoload.php';

  $gree = new \Gree\GreeAC(); 
  $gree->setDebug(true); // If you want to see all
  $gree->setCID('f4211ede6d31'); // Replace to your own cid
  
  $key = $gree->getBindKey();
  
  echo 'Secure key is: '.$key;
?>
```
7. Run from console "php bind.php" you will see response: 
Secure key is: Lz5Op8Rs2Uv4Xy5A
8. Save this key to somewhere
9. Add AC to Gree+ app (it will add it to your WiFi router)
10. Get IP adress of AC from your WiFi router (for example it 192.168.10.3)
11. Write simple script like test.php:
```php
<?php
  require_once 'vendor/autoload.php';

  $gree = new \Gree\GreeAC('192.168.10.3', 'f4211ede6d31', 'Lz5Op8Rs2Uv4Xy5A'); // Replace values to yours
  $gree->setDebug(true); // If you want to see all
  
  print_r($gree->status()); // Will show current settings
  print_r($gree->on()); // Switch on AC
  sleep(3);
  print_r($gree->off()); // Switch off AC
  // ...etc
?>
