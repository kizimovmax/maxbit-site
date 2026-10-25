<?php
/**
 * Локальный тестовый приёмник (заменяет Битрикс24 при проверке адаптера).
 * Запуск: php -S 127.0.0.1:8099 scripts/test-receiver.php
 * Режимы (?mode=): ok — {"ok":true,"received":<request_id>}; fail — 500; slow — 8 с (тайм-аут);
 * badjson — 200 «OK»; okfalse — {"ok":"false"}; resultfalse — {"result":false}; resultempty — {"result":[]};
 * errorresult — {"error":"x","result":false}; wrongid — ok:true, но чужой request_id;
 * b24ok — {"result":123}; b24err — {"error":"..."}; b24zero — {"result":0}.
 * Журнал: logs/receiver.log — только request_id, попытка и режим, без контактов.
 */
$mode = $_GET['mode'] ?? 'ok';
$body = json_decode( file_get_contents( 'php://input' ), true ) ?: array();
$rid  = $body['request_id'] ?? '-';
file_put_contents( __DIR__ . '/../logs/receiver.log', gmdate( 'c' ) . " mode=$mode request_id=$rid attempt=" . ( $body['attempt'] ?? '-' ) . "\n", FILE_APPEND );
header( 'Content-Type: application/json' );
switch ( $mode ) {
	case 'fail':        http_response_code( 500 ); echo '{"error":"simulated"}'; break;
	case 'slow':        sleep( 8 ); echo json_encode( array( 'ok' => true, 'received' => $rid ) ); break;
	case 'badjson':     echo 'OK'; break;
	case 'okfalse':     echo '{"ok":"false"}'; break;
	case 'resultfalse': echo '{"result":false}'; break;
	case 'resultempty': echo '{"result":[]}'; break;
	case 'errorresult': echo '{"error":"synthetic_error","result":false}'; break;
	case 'wrongid':     echo '{"ok":true,"received":"MB-000000-XXXXXX"}'; break;
	case 'b24ok':       echo '{"result":123}'; break;
	case 'b24err':      echo '{"error":"ERROR_CORE","error_description":"x"}'; break;
	case 'b24zero':     echo '{"result":0}'; break;
	default:            echo json_encode( array( 'ok' => true, 'received' => $rid ) );
}
