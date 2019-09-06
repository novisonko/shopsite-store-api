<?php
ini_set('max_execution_time', 0); // for infinite time of execution 

/**
 * Execute action and respond to API request
 * 
 * @author: Novi Sonko
 * @email: novisonko@sonkotek.com
 * @url: https://sonkotek.com
 */
require_once(dirname(__FILE__) . '/bootstrap.php');

/*@dev Log
$fhandle= fopen(dirname(__FILE__).'/log.dat', "a+");
fwrite($fhandle, 'GET: ' . date('d m Y H:i:s') . var_export($_GET, true) . PHP_EOL);
fwrite($fhandle, 'POST: ' . date('d m Y H:i:s') . var_export($_POST, true) . PHP_EOL);
fwrite($fhandle, 'php://input: ' . date('d m Y H:i:s') . var_export(file_get_contents('php://input'), true) . PHP_EOL);
*/

use \Sonkotek\Data\DatabaseConnectLite;

$request= [];

// populate request
switch (strtolower($_SERVER['REQUEST_METHOD'])){

	case "post":

		switch($_SERVER["CONTENT_TYPE"]){

			case "application/json":

				$request= json_decode(file_get_contents('php://input'), true);

			break;

            case "application/x-www-form-urlencoded":                
			case "application/text":

				parse_str(file_get_contents('php://input'), $request);

			break;
		}

	break;

	case "get":

		$request= $_GET;

	break;
}

$results= [];
$response= '';
$start= 0;
$numrows= 50;

if(!is_array($request) || (count($request) === 0)){
    error_log("Request is empty");
    exit;
} 

/*@dev
fwrite($fhandle, 'Request is: ' . date('d m Y H:i:s') . var_export($request, true) . PHP_EOL);

fclose($fhandle);
*/

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

if(isset($request['inv_status'])){

    \Sonkotek\requireNotEmpty($request, 'serialnum,storeid,item_total');

    switch($request['inv_status']){

        case "level":
        case "available":
        case "test":

            if('test' === $request['inv_status']){
                
                $request['item_total']= 1;

                $query0= 'SELECT * FROM item_quantity ORDER BY item_id DESC LIMIT 2;';
                
                $data0= $conn2->execute($query0);
                
                if(($data0['numrows']>0) && isset($data0['results'][0]['sku'])){
                
                    $request['p1sku']= $data0['results'][0]['sku'];

                }

            }

            if(($total= $request['item_total'])>0){

                $response .= "product_count={$total}\n";

                for($i=1;$i<=$total;$i++){

                    if(isset($request[$key='p'.$i.'sku'])){

                        $sku= $request[$key];

                        $query1= "SELECT quantity FROM item_quantity WHERE sku='".trim($sku)."';";

                        $data1= $conn2->execute($query1);

                        if(($data1['numrows']>0) && isset($data1['results'][0]['quantity'])){
                        
                            $response .= "p{$i}quantity=".$data1['results'][0]['quantity']."\n";
                        
                        }
                    }
                }
            }

            if(strlen($response)>0){
                echo $response;
                exit;
            }

            error_log("Empty response for available request");
            
        break;

        case "purchased":   
        
            $results['sold']= [];
            $results['closed']= [];

            if(($total= $request['item_total'])>0){

                for($i=1; $i<=$total;$i++){

                    $sku= null;
                    $quantityOrdered= null;

                    if(isset($request['p'.$i.'sku'])){

                        $sku= $request['p'.$i.'sku'];
                        $quantityOrdered= $request['p'.$i.'quantity'];
                        
                        if(!empty($request['itemSoldRef'])){
                            $itemSoldRef= $request['itemSoldRef'];
                        } else {

                            $query0= "SELECT quantity FROM item_quantity WHERE sku='".$sku."';";
                            $data0= $conn2->execute($query0);

                            if(isset($data0['results'][0]['quantity'])){

                                $itemSoldRef= $sku . '_' . $data0['results'][0]['quantity'];

                            } else {

                                $itemSoldRef=  $sku . '_' . 1;

                            }
                        }

                        $query1= "SELECT * FROM item_sold WHERE item_sold_ref='".$itemSoldref."';";
                        $data1= $conn2->execute($query1);

                        if($data1['numrows'] === 0){

                            $query2= "UPDATE item_quantity SET quantity=(quantity-".(int)$quantityOrdered.") WHERE sku='".trim($sku)."';";

                            $data2= $conn2->execute($query2);

                            $query3= "INSERT INTO item_sold(sku,quantity,date_created,item_sold_ref) 
                            VALUES('".trim($sku)."', ".(int)$quantityOrdered.", now(),'".$itemSoldRef."');";

                            $data3= $conn2->execute($query3);

                            $results['sold'][]= [
                                'sku' => $sku,
                                'quantitySold' => (int)$quantityOrdered
                            ];
                        }

                        $query4= "SELECT quantity FROM item_quantity WHERE sku='".$sku."';";

                        $data4= $conn2->execute($query4);

                        if(isset($data4['results'][0]['quantity'])){

                            // add to item closed
                            if((int)$data4['results'][0]['quantity'] <= 0){

                                $query5= "INSERT IGNORE INTO item_closed(sku,date_created) 
                                VALUES(:sku, now());";
                
                                $data5= $conn2->query($query5, [
                                    'sku' => $sku
                                ]);
                            }

                            $results['closed'][]= [
                                'sku' => $sku,
                                'quantity' => $data4['results'][0]['quantity']
                            ];

                            // add item sold message
                            \Sonkotek\addItemSoldMessage($sku);
                        }
                    }
                }
            }

            echo json_encode($results);
            exit;

        break;

    }

} else if (isset($request['action'])){

    if(isset($request['numberOfItems'])){

        $numrows= (int)$request['numberOfItems'];
        
    }

    if(isset($request['startingItem'])){

        $start= (int)$request['startingItem'];

        if($start === 1){
            $start= 0;
        }               
    }

    switch($request['action']){

        case "selectWithSKU":

            if(empty($request['sku'])){
                echo "No sku submitted";
                exit;
            }

            $query0= "SELECT * FROM item_quantity WHERE sku=:sku;";

            $data0= $conn2->query($query0, [
                'sku' => $request['sku']
            ]);

            $query1= "SELECT * FROM ss_Products WHERE ss_Name=:sku;";
    
            $data1= $conn1->query($query1, [
                'sku' => $request['sku']
            ]);

            $res= [];

            if(!empty($data0['results'][0]['sku']) ){

                $res= array_merge($res, $data0['results'][0]);

            }

            if(!empty($data1['results'][0]['ss_SKU'])){

                $res= array_merge($res, $data1['results'][0]);

            }

            if(!empty($res)){
               
                echo json_encode($res);
                exit;

            } else {

                echo "No result";
                exit;
            }

        break;


        case "selectActive":

            $query0= "SELECT * FROM ss_Products ORDER BY ss_ProductsId DESC LIMIT $numrows OFFSET $start";
    
            $data0= $conn1->execute($query0);

            if($data0['numrows']>0){

                $data0['results']= \Sonkotek\filterItems($data0['results'], $request['action']);
            
                echo json_encode($data0['results']);
                exit;

            } else {

                echo "No result";
                exit;
            }

        break;

        case "selectSold":

            $query0= "SELECT * FROM item_sold ORDER BY item_sold_id DESC LIMIT $numrows OFFSET $start;";
                
            $data0= $conn2->execute($query0);
            
            if($data0['numrows']>0){
            
                echo json_encode($data0['results']);
                exit;

            } else {

                echo "No result";
                exit;
            }

        break;

        case "selectClosed":

            $query0= "SELECT * from ss_OrderProduct WHERE ss_ProductType='Tangible' order by ss_OrderProductID DESC LIMIT $numrows OFFSET $start";

            $data0= $conn1->execute($query0);
            
            if($data0['numrows']>0){

                $results= $data0['results'];

            }            

            $query1= "SELECT sku as ss_SKU, item_closed.* FROM item_closed ORDER BY item_closed_id DESC LIMIT $numrows OFFSET $start;";
                
            $data1= $conn2->execute($query1);
            
            if($data1['numrows']>0){
            
                $results= array_merge($data1['results'], $results);

            }
            
            if(count($results)>0){
                
                echo json_encode($results);
                exit;

            } else {

                echo "No result";
                exit;
            }

        break;

        case "closeItem":

            if(empty($request['sku'])){
                echo "No sku submitted";
                exit;
            } else {
                $sku= $request['sku'];
            }

            $query0= "SELECT * FROM item_closed WHERE sku=:sku;";

            $data0= $conn2->query($query0, [
                'sku' => $request['sku']
            ]);

            if(!isset($data0['results'][0]['sku'])){          
    
                $query1= "INSERT INTO item_closed(sku,date_created) 
                VALUES(:sku, now());";

                $data1= $conn2->query($query1, [
                    'sku' => $sku
                ]);
            }

            $query2= "INSERT INTO item_quantity(sku,quantity) VALUES(:sku,0) ON DUPLICATE KEY UPDATE quantity=0";

            $data2= $conn2->query($query2, [
                'sku' => $sku
            ]);

            $itemSoldRef=  $sku . '_' . 0;

            $query3= "INSERT INTO item_sold(sku,quantity,date_created,item_sold_ref) 
            VALUES('".trim($sku)."', 0, now(),'".$itemSoldRef."') 
            ON DUPLICATE KEY UPDATE quantity=0;";

            $data3= $conn2->execute($query3);
                        
            if(($data0['numrows']>0) || ($data1['numrows']>0)){

                // add item sold message
                \Sonkotek\addItemSoldMessage($sku);

                echo json_encode([
                    'sku' => $sku
                ]);

                exit;

            } else {

                echo "Action failed";

                exit;
            }

        break;

        case "dumpAll":

            $max= 10000;
            $numrows= 50;
            $start= 0;
            $failed= 0;
            $results= [];
            $file= dirname(__FILE__) . '/database.dat';

            file_put_contents($file, '');

            for($i=0; $i <$max; $i++){

                $query0= "SELECT * FROM ss_Products ORDER BY ss_ProductsId DESC LIMIT $numrows OFFSET $start";

                $start += $numrows;
            
                $data0= $conn1->execute($query0);

                if($data0['numrows']>0){

                    echo "{$i}: found {$data0['numrows']} rows <br>";

                    file_put_contents($file, json_encode($data0['results']) . PHP_EOL . "=//=" . PHP_EOL, FILE_APPEND);

                } else {

                    echo "{$i}: No result<br>";
                    $failed++;
                }

                if($failed>10){
                    echo "Failed $failed times<br>";
                    exit;
                }
            }

    break;

    }

}