<?php
/**
 * Transfer data between databases
 * 
 * @author: Novi Sonko
 * @email: novisonko@sonkotek.com
 * @url: https://sonkotek.com
 */

require_once(dirname(__FILE__) . '/../bootstrap.php');

/* log
$fhandle= fopen(dirname(__FILE__).'/log.dat', "a+");
fwrite($fhandle, date('d m Y H:i:s') . PHP_EOL);
fclose($fhandle);
*/

use \Sonkotek\Data\DatabaseConnectLite;

$inserted= 0;
$updated= 0;
$fail= 0;
$start= 0;
$batchSize= 1000;
$already= 0;
$break= false;

$conn1= new \Sonkotek\Data\DatabaseConnectLite();

$conn1->setFile(\DB_FILE);

$conn1->connect([
    'type' => 'sqlite'
]);

$conn2= new \Sonkotek\Data\DatabaseConnectLite();
$conn2->connect([
    'type' => 'mysql',
    'database' => \DATABASE,
    'user' => \USER,
    'password' => \PASSWORD
]);

while(true){

    echo "Selecting $batchSize items<br>\n";

    $query0= "SELECT * FROM ss_Products ORDER BY ss_ProductsId DESC LIMIT $batchSize OFFSET $start";

    $data0= $conn1->execute($query0);

    if($data0['numrows']>0){

        foreach($data0['results'] as $row){

            $query1= "SELECT * FROM item_quantity WHERE sku='".trim($row['ss_SKU'])."';";

            $data1= $conn2->execute($query1);

            if($data1['numrows'] == 0){

                $query2= "INSERT IGNORE INTO item_quantity(sku,quantity) VALUES('".trim($row['ss_SKU'])."', ".(int)$row['ss_QuantityOnHand'].");";

                $data2= $conn2->execute($query2);

                if($data2['numrows']>0){

                    $inserted++;

                    echo "Added item {$row['ss_SKU']}, number of successful actions is $inserted<br>\n";
                }

            } else {

                $query3= "SELECT * FROM item_closed WHERE sku='".trim($row['ss_SKU'])."';";

                $data3= $conn2->execute($query3);

                if($data3['numrows'] >= 1){
                    $row['ss_QuantityOnHand']= 0;
                }
            
                if(isset($data1['results'][0]['quantity']) && ((int)$data1['results'][0]['quantity'] !== (int)$row['ss_QuantityOnHand'])){
                
                    $query4= "UPDATE item_quantity SET quantity= ".(int)$row['ss_QuantityOnHand']." WHERE sku='".trim($row['ss_SKU'])."';";

                    $data4= $conn2->execute($query4);

                    if($data4['numrows']>0){

                        echo "Updated item {$row['ss_SKU']}, quantity is ".(int)$row['ss_QuantityOnHand']."<br>\n";
                        $updated++;

                    }

                }
            }

            $data1= [];
            $data2= [];
        }

    } else {

        $fail++;

        if($fail > 5){

            echo "No result for select query, aborting<br>\n";

            break;
        }

    }

    $start += $batchSize;

    if($break){
        break;
    }
}


echo "<br>\nEND: Added $inserted items<br>\n";
echo "<br>\nEND: Updated $updated items<br>\n";