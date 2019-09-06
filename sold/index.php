<?php
/**
 * Add sold item messages
 * 
 * @author: Novi Sonko
 * @email: novisonko@sonkotek.com
 * @url: https://sonkotek.com
 */

require_once(dirname(__FILE__) . '/../bootstrap.php');

use \Sonkotek\Data\DatabaseConnectLite;

$already= 0;
$fail= 0;
$success= 0;

$break= false;

$start= 0;
$batchSize= 100;

$conn1= new \Sonkotek\Data\DatabaseConnectLite();
$conn1->connect([
    'type' => 'mysql',
    'database' => \DATABASE,
    'user' => \USER,
    'password' => \PASSWORD
]);

while(true){

    echo "Selecting $batchSize items<br>\n";

    $query0= "SELECT sku FROM item_closed ORDER BY item_closed_id DESC LIMIT $batchSize OFFSET $start";

    $data0= $conn1->execute($query0);

    if($data0['numrows']>0){

        foreach($data0['results'] as $row){

           // add item sold message
           $status= \Sonkotek\addItemSoldMessage($row['sku']);

          if('already' === $status){
               $already++;
           } else if ('success' === $status){
                $success++;
           } else {
               $fail++;
           }

          if($already > 10){
            echo "<br>\nEND: Reached maximum of $already updated items<br>\n";
            $break= true;
            break;
           }

        }

    } else {
        $break= true;
    }

    if($break){
        break;
    }

    $start += $batchSize;
}

echo "<br>\nEND: $already already results<br>\n";
echo "<br>\nEND: Updated $success items<br>\n";
echo "<br>\nEND: $fail failed actions<br>\n";