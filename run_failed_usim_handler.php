<?php
// 디버깅 모드 활성화
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL | E_STRICT);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 타임존 설정
date_default_timezone_set("Asia/Seoul");

// 데이터베이스 연결 정보
require "/var/www/html8443/mallapi/db_info.php";
$db_conn = mysqli_connect($db_host, $db_user, $db_pwd, $db_category, $db_port);
if (mysqli_connect_errno()) {
    die("DB 연결 실패: " . mysqli_connect_error());
}

// 요청 파라미터 확인
if (!$_REQUEST['retry_start_dt'] || !$_REQUEST['retry_end_dt']) {
    echo "<script>alert('날짜 파라미터가 필요합니다.'); window.history.back();</script>";
    exit;
}
// 날짜 파라미터를 안전하게 처리
$start_date = $_REQUEST['retry_start_dt'];
$end_date = $_REQUEST['retry_end_dt'];

// 날짜 기반 및 API 상태를 기준으로 실패한 예약 데이터 조회
$query = "SELECT * FROM tb_pickup_order_item_sk 
          WHERE api_state = 0 
          AND send_api_time BETWEEN '{$start_date} 00:00:00' AND '{$end_date} 23:59:59'";

$result = mysqli_query($db_conn, $query);

// 쿼리 실행 오류 처리
if (!$result) {
    die("쿼리 실패: " . mysqli_error($db_conn));
}

// 결과가 없을 경우 사용자에게 알림
if (mysqli_num_rows($result) == 0) {
    echo "<script>
        alert('선택한 기간 내에 실패한 예약 데이터가 없습니다.');
        window.location.href = 'ktmsim_pickup_order_skt.php';
    </script>";
    exit;
}


// 로그 파일 이름 및 경로 설정
$api3_log_request_file_name = '점검이후_발송_API3_요청'.'_'.date("Ymd").'log.txt';
$api3_log_response_file_name = '점검이후_발송_API3_응답'.'_'.date("Ymd").'log.txt';
$api3_log_error_file_name = '점검이후_발송_API3_에러'.'_'.date("Ymd").'log.txt';


$api3_log_request_path = "/var/www/html/mobile_app/mgr/logs/api3/request/";
$api3_log_response_path = "/var/www/html/mobile_app/mgr/logs/api3/response/";
$api3_log_error_path = "/var/www/html/mobile_app/mgr/logs/api3/error/";

// 실패/성공 주문 코드 초기화
$failed = array();   // 실패 주문 코드 모으기
$success = 0;        // 성공 건수


// API3 요청 및 응답 및 로그 처리 (핵심 로직)
while ($row = mysqli_fetch_assoc($result)) {

    // 주문 아이템 코드가 없으면 건너뛰기
    $query1 = "SELECT * FROM sk_api_send_pickup WHERE order_item_code='{$row['order_item_code']}'";
    $result1 = mysqli_query($db_conn, $query1);
    if (!$result1 || !($row1 = mysqli_fetch_assoc($result1))) {
        log_write($row['order_item_code'], 'ref data missing', $api3_log_error_file_name, $api3_log_error_path);
        $failed[] = $row['order_item_code'];
        continue;
    }


    // 필요한 데이터 추출
    $rental_schd_sta_dtm = $row1['rental_schd_sta_dtm'];
    $rental_schd_end_dtm = $row1['rental_schd_end_dtm'];
    $rental_sale_org_id = $row1['rental_sale_org_id'];
    $rtn_sale_org_id = $row1['rtn_sale_org_id'];
    $rsv_rcv_dtm = $row1['rsv_rcv_dtm'];
    $rental_booth_org_id = $row1['rental_booth_org_id'];
    $mst_flag = $row1['mst_flag'];
    $product_code = $row1['product_code'];


    // 필요한 데이터
    $company = "프리피아";
    $sk_api3_type = "api3";
    $flag = "I";
    $product_name = $row['product_name'];
    $product_day = $row['product_day'];
    $pickup_date = $row['pickup_date'];
    $sk_api_url = "https://223.62.242.91/api/swinghub";
    $order_item_code = $row['order_item_code'];
    $email = 'cs@prepia.co.kr'; // 이메일
    $buy_user_name = $row['buy_user_name'];
    $shop_no = $row['shop'];
    $passport = $row['passport'];
    $paymentDT = date('YmdHis');
    // 쇼핑몰별 추천인ID
    if($shop_no == 11){
        $rcmndr_id = "1313042946";
    }else{
        $rcmndr_id = "1313033433";
    }
    $total_cnt = '1'; // 총 개수
    $order_ymd = date('Ymd');

    // 상품 관련 정보 설정
    switch($product_name) {
        case "1": {
            $option_date = $product_day;
            $roming_typ_cd = "16"; // 로밍 타입 코드
            $eqp_mdl_cd = '';

            switch( $product_day ) {
                case 1 :	$rental_fee_prod_id = "NA00005913"; break;
                case 2 :    $rental_fee_prod_id = "NA00008808"; break;
                case 3 :	$rental_fee_prod_id = "NA00008254"; break;
                case 4 :    $rental_fee_prod_id = "NA00008809"; break;
                case 5 :	$rental_fee_prod_id = "NA00008255"; break;
                case 6 :    $rental_fee_prod_id = "NA00008810"; break;
                case 7 :    $rental_fee_prod_id = "NA00008773"; break;
                case 8 :    $rental_fee_prod_id = "NA00008811"; break;
                case 9 :    $rental_fee_prod_id = "NA00008812"; break;
                case 10 :	$rental_fee_prod_id = "NA00008256"; break;
                case 15 :   $rental_fee_prod_id = "NA00008774"; break;
                case 20 :	$rental_fee_prod_id = "NA00008257"; break;
                case 30 :   $rental_fee_prod_id = "NA00008258"; break;
                case 60 :   $rental_fee_prod_id = "NA00008775"; break;
                case 90 :   $rental_fee_prod_id = "NA00008776"; break;
            }
            // 종료예정일 생성:  카페24의 날짜 데이터를 date 타입으로 변환해서 상품옵션 일자를 더해서 계산
        }
            break;
        case "3": {
            $roming_typ_cd ="15"; // 로밍 타입 코드
            $eqp_mdl_cd = 'A00B';
            $rental_fee_prod_id = 'NA00004421';
            $option_date = $product_day;

        }
            break;

        case "4": {
            $roming_typ_cd = '16';
            $eqp_mdl_cd = '';
            $option_date = $product_day;

            switch( $product_day ) {
                case "1" : $rental_fee_prod_id = "NA00007679"; break; // 추가됨 (레드 eSIM 1일)
                case "2" : $rental_fee_prod_id = "NA00008813"; break; // 추가됨 (레드 eSIM 2일)
                case "3" : $rental_fee_prod_id = "NA00008249"; break;
                case "4" : $rental_fee_prod_id = "NA00008814"; break; // 추가됨 (레드 eSIM 4일)
                case "5" : $rental_fee_prod_id = "NA00008250"; break;
                case "6" : $rental_fee_prod_id = "NA00008815"; break; // 추가됨 (레드 eSIM 6일)
                case "7" : $rental_fee_prod_id = "NA00008777"; break;
                case "8" : $rental_fee_prod_id = "NA00008816"; break; // 추가됨 (레드 eSIM 8일)
                case "9" : $rental_fee_prod_id = "NA00008817"; break; // 추가됨 (레드 eSIM 9일)
                case "10" : $rental_fee_prod_id = "NA00008251"; break;
                case "15" : $rental_fee_prod_id = "NA00008778"; break;
                case "20" : $rental_fee_prod_id = "NA00008252"; break;
                case "30" : $rental_fee_prod_id = "NA00008253"; break;
                case "60" : $rental_fee_prod_id = "NA00008779"; break;
                case "90" : $rental_fee_prod_id = "NA00008780"; break;
            }

            // 종료예정일 생성:  카페24의 날짜 데이터를 date 타입으로 변환해서 상품옵션 일자를 더해서 계산
        }
            break;

    }

    // api3 요청 데이터 생성
    $data = '{ "company" : "프리피아", "apiType": "'.$sk_api3_type.'",
					"IN": [{
					"flag":"'.$flag.'",
					"rental_schd_sta_dtm":"'.$rental_schd_sta_dtm.'",
					"rental_schd_end_dtm":"'.$rental_schd_end_dtm.'",
					"rental_sale_org_id":"'.$rental_sale_org_id.'",
					"rtn_sale_org_id":"'.$rtn_sale_org_id.'",
					"email_addr":"'.$email.'",
					"rsv_rcv_dtm":"'.$rsv_rcv_dtm.'",
					"rental_booth_org_id":"'.$rental_booth_org_id.'",
					"roming_passport_num":"'.$passport.'",
					"cust_nm":"'.$buy_user_name.'",
					"rcmndr_id":"'.$rcmndr_id.'",
					"total_cnt":"1"
					}],
					"IN1":[{
							"mst_flag":"'.$mst_flag.'",
							"roming_typ_cd":"'.$roming_typ_cd.'",
							"rsv_vou_num":"'.$order_item_code.'",
							"eqp_mdl_cd":"'.$eqp_mdl_cd.'",
							"rental_fee_prod_id":"'.$rental_fee_prod_id.'"
							}]
				}';

    // 로그 기록
    log_write($order_item_code, $data, $api3_log_request_file_name, $api3_log_request_path);

    // cURL 초기화 및 설정
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $sk_api_url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' ) );
    curl_setopt( $ch, CURLOPT_POST, true);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);



    // 요청 시간 기록
    $apiDTS = date("Y-m-d H:i:s");
    $res = curl_exec( $ch );
    if($res){
        $apiDTR = date("Y-m-d H:i:s");
    }

    // cURL 오류 처리
    curl_close( $ch );
    $resData = json_decode( $res, true );

    // 응답 데이터 기록
    log_write($order_item_code,$resData, $api3_log_response_file_name, $api3_log_response_path);
    // ❶ 파싱 실패 또는 OUT1 누락 == 응답 자체 문제
    if (!$resData || !isset($resData['OUT1'])) {
        log_write($order_item_code, $res, $api3_log_error_file_name, $api3_log_error_path);
        $failed[] = $order_item_code;
        continue;            // 다음 주문으로
    }


    // SK API 전송 데이터
    $query = "INSERT INTO sk_api_send_pickup (order_item_code, product_code, flag, rental_schd_sta_dtm, rental_schd_end_dtm, rental_sale_org_id, rtn_sale_org_id, email_addr, rsv_rcv_dtm, rental_booth_org_id, roming_passport_num, cust_nm, rcmndr_id, total_cnt, mst_flag, roming_typ_cd, rsv_vou_num, rental_fee_prod_id) 
						VALUES ('$order_item_code','$product_code','$flag','$rental_schd_sta_dtm','$rental_schd_end_dtm','$rental_sale_org_id','$rtn_sale_org_id','$email','$rsv_rcv_dtm','$rental_booth_org_id','$passport','$buy_user_name','$rcmndr_id','$total_cnt','$mst_flag','$roming_typ_cd','$order_item_code','$rental_fee_prod_id')";
    // 쿼리 실행
    try {
        mysqli_query($db_conn, $query);
    } catch (mysqli_sql_exception $e) {
        log_write($order_item_code, $e->getMessage(),
            $api3_log_error_file_name, $api3_log_error_path);
        $failed[] = $order_item_code;
        continue;
    }

    // API 리턴값 오류 확인
    if(!$resData['OUT1']){ // api 오류

        // 리턴값 DB 저장 X, 로그 파일에만 저장
        // SK API3 관리자 페이지용 데이블 저장, 자동 api는 오류 메세지 저장
        $note = $res;
        $api_state = 0;
        $ctn = '';
        $query = "INSERT INTO tb_pickup_order_item_sk (order_id, order_item_code, shop, product_name, product_day, quantity, pickup_date, buy_user_name, passport, pickup_place, note, api_state, send_api_time, return_api_time, ctn, buy_user_memo, order_ymd) 
								VALUES ('$order_item_code','$order_item_code','$shop_no','$product_name','$product_day','$total_cnt','$rental_schd_sta_dtm','$buy_user_name','$order_item_code','$rental_sale_org_id','$note', '$api_state', '$apiDTS', '$apiDTR', '$ctn', '$note','$order_ymd')";


        // 쿼리 실행 및 예외 처리
        try {
            mysqli_query($db_conn, $query);
        } catch (mysqli_sql_exception $e) {
            log_write($order_item_code, $e->getMessage(),
                $api3_log_error_file_name, $api3_log_error_path);
            $failed[] = $order_item_code;
            continue;

        }

    }else{  // api 성공

        // 리턴값 DB 저장
        return_data_save($db_conn, $resData,$order_item_code,$failed,
            $api3_log_error_file_name, $api3_log_error_path);

        // SK API3 관리자 페이지용 데이블 저장
        $note = '';
        $api_state = 1;
        foreach( $resData['OUT2'] as $out2 ) {
            $ctn = $out2['ROMING_NUM'];
        }
        $option_date = preg_replace("/\s+/", "", $option_date);
        $query = "INSERT INTO tb_pickup_order_item_sk (order_id, order_item_code, shop, product_name, product_day, quantity, pickup_date, buy_user_name, passport, pickup_place, note, api_state, send_api_time, return_api_time, ctn, buy_user_memo, order_ymd) 
								VALUES ('$order_item_code','$order_item_code','$shop_no','$product_name','$product_day','$total_cnt','$rental_schd_sta_dtm','$buy_user_name','$order_item_code','$rental_sale_org_id','$note', '$api_state', '$apiDTS', '$apiDTR', '$ctn', '$note','$order_ymd')";

        $result = mysqli_query( $db_conn, $query );
        if( !$result ){
            echo "[DB ERROR] $query \n". mysqli_error( $db_conn ) . " \n";
        }

        $success++;

    }
}
// 데이터베이스 연결 종료
mysqli_close($db_conn);


//
function return_data_save($db_conn, $resData, $order_item_code,
                          $failed,
                          $error_log_file_name, $error_log_path)
{
    /* --- 1) OUT1 최소 필드 확보 여부 확인 --- */
    if (empty($resData['OUT1'][0]['TOTAL_CNT']) ||
        empty($resData['OUT1'][0]['RENTAL_MGMT_NUM'])) {

        log_write($order_item_code,
            'OUT1 missing essential fields',
            $error_log_file_name, $error_log_path);
        $failed[] = $order_item_code;
        return;                 // 더 진행할 필요 없음
    }

    $total_cnt       = (int)$resData['OUT1'][0]['TOTAL_CNT'];
    $rental_mgmt_num = mysqli_real_escape_string(
        $db_conn,
        $resData['OUT1'][0]['RENTAL_MGMT_NUM']
    );

    /* --- 2) OUT2 레코드 각각 INSERT --- */
    if (empty($resData['OUT2']) || !is_array($resData['OUT2'])) {
        log_write($order_item_code,
            'OUT2 empty',
            $error_log_file_name, $error_log_path);
        $failed[] = $order_item_code;
        return;
    }

    foreach ($resData['OUT2'] as $out2) {

        $rental_mst_num = isset($out2['RENTAL_MST_NUM'])
            ? mysqli_real_escape_string($db_conn, $out2['RENTAL_MST_NUM'])
            : '';
        $roming_num = isset($out2['ROMING_NUM'])
            ? mysqli_real_escape_string($db_conn, $out2['ROMING_NUM'])
            : '';

        $sql = "
            INSERT INTO sk_api_return_pickup
            (order_item_code, rental_mst_num, roming_num, total_cnt, rental_mgmt_num)
            VALUES ('$order_item_code', '$rental_mst_num', '$roming_num',
                    $total_cnt, '$rental_mgmt_num')
        ";

        try {
            mysqli_query($db_conn, $sql);
        } catch (mysqli_sql_exception $e) {
            /* ↳ SQL 오류가 나도 다음 OUT2 레코드를 처리해야 하므로
                  로그만 남기고 foreach 를 계속 돈다 */
            log_write($order_item_code, $e->getMessage(),
                $error_log_file_name, $error_log_path);
            $failed[] = $order_item_code;
            // continue; ← foreach 안이므로 사용 가능 (명시적일 필요 X)
        }
    }
}


//// 로그 파일에 기록하는 함수
function log_write($order_item_code, $log_data, $file_name, $log_dir)
{
    /* 1) 경로 안전 조합 */
    $dir = rtrim($log_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    /* 2) 디렉터리 생성(775/664) */
    $oldUmask = umask(002);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true)) {
            umask($oldUmask);
            throw new RuntimeException("로그 디렉터리 생성 실패: {$dir}");
        }
    }
    umask($oldUmask);

    /* 3) 로그 라인 생성 */
    $time = date('c');                                   // ISO-8601
    $sanitized = preg_replace('/[^A-Za-z0-9_\-]/', '', $order_item_code);

    if (is_string($log_data)) {
        $body = $log_data;
    } else {
        $body = json_encode($log_data, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            // PHP 5.3~5.4에 json_last_error_msg()가 없으므로 별도 처리
            $jsonError = function_exists('json_last_error_msg')
                ? json_last_error_msg()
                : 'JSON encoding error';
            throw new RuntimeException('JSON 인코딩 실패: ' . $jsonError);
        }
    }

    $logTxt = "\n({$sanitized} {$time})\n{$body}\n\n";

    /* 4) 파일 쓰기(append + lock) */
    $path = $dir . $file_name;

    // 10 MB 초과 시 간단 로테이션
    if (is_file($path) && filesize($path) > 10 * 1024 * 1024) {
        rename($path, $path . '.' . date('Ymd_His'));
    }

    // LOCK_EX 상수는 PHP 5.1 이상에서 지원
    if (file_put_contents($path, $logTxt, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException("로그 파일 쓰기 실패: {$path}");
    }

    return true;
}
// 모든 로직이 정상적으로 완료되었을 때 아래 코드 실행
if ($failed) {
    $cnt   = count($failed);
    $list  = implode(', ', $failed);
    echo "<script>
        alert('성공 {$success}건 / 실패 {$cnt}건 (코드: {$list})');
        location.href='ktmsim_pickup_order_skt.php';
    </script>";
} else {
    echo "<script>
        alert('API 재전송 완료! (성공 {$success}건)');
        location.href='ktmsim_pickup_order_skt.php';
    </script>";
}
exit;
?>