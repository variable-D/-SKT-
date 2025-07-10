<?php
// 디버깅 모드 활성화
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 타임존 설정
date_default_timezone_set("Asia/Seoul");

// 데이터베이스 연결 설정
// 공통 함수
require'/var/www/html/mobile_app/mgr/common.php';

// 데이터베이스 연결
$db_conn = get_db_connection();

// 필요한 라이브러리 포함
include_once "/var/www/html/mobile_app/mgr/phpbarcode/src/BarcodeGeneratorPNG.php";
include_once "/var/www/html/mobile_app/mgr/phpqrcode/qrlib.php";

// 날짜 파라미터 확인
if (!$_REQUEST['retry_start_dt'] || !$_REQUEST['retry_end_dt']) {
    echo "날짜 파라미터가 필요합니다.";
    die;
}

// 날짜 파라미터를 안전하게 처리
$start_date = $_REQUEST['retry_start_dt'];
$end_date = $_REQUEST['retry_end_dt'];

// 쿼리문 작성
$query = "SELECT * FROM tb_esim_order_item_sk_red 
          WHERE email_state = 0 
          AND send_api_time BETWEEN '{$start_date} 00:00:00' AND '{$end_date} 23:59:59'";

$rs = mysqli_query($db_conn, $query);

// 성공/실패 목록 초기화
$failed = array();
$success = 0;

// 로그 파일 이름 및 경로 설정
$api7_log_request_file_name = '점검이후_발송_API7_요청'.'_'.date("Ymd").'log.txt';
$api7_log_response_file_name = '점검이후_발송_API7_응답'.'_'.date("Ymd").'log.txt';
$api7_log_error_file_name = '점검이후_발송_API7_에러'.'_'.date("Ymd").'log.txt';
$api6_log_request_file_name = '점검이후_발송_API6_요청'.'_'.date("Ymd").'log.txt';
$api6_log_response_file_name = '점검이후_발송_API6_응답'.'_'.date("Ymd").'log.txt';
$api6_log_error_file_name = '점검이후_발송_API6_에러'.'_'.date("Ymd").'log.txt';

$api6_log_request_path = "/var/www/html/mobile_app/mgr/logs/api6/request/";
$api6_log_response_path = "/var/www/html/mobile_app/mgr/logs/api6/response/";
$api6_log_error_path = "/var/www/html/mobile_app/mgr/logs/api6/error/";
$api7_log_request_path = "/var/www/html/mobile_app/mgr/logs/api7/request/";
$api7_log_response_path = "/var/www/html/mobile_app/mgr/logs/api7/response/";
$api7_log_error_path = "/var/www/html/mobile_app/mgr/logs/api7/error/";

if (!$rs) {
    die("쿼리 실패: " . mysqli_error($db_conn));
}

if (mysqli_num_rows($rs) === 0) {
    echo "<script>alert('조회된 데이터가 없습니다.'); history.back();</script>";
    exit;
}

// API 요청 및 응답 처리 (핵심 로직)
while ($row = mysqli_fetch_assoc($rs)) {


    // 필요한 데이터 추출
    $company = "프리피아";
    $sk_api6_type = "api6";
    $sk_api7_type = "api7";
    $roming_typ_cd = "16"; // 로밍 타입 코드
    $sk_api_url = "https://223.62.242.91/api/swinghub";
    $post_sale_org_id = 'V992470000'; //소속영업조직ID
    $dom_cntc_num = '0000'; //국내연락전화번호
    $order_item_code = $row['order_item_code'];
    $email = 'cs@prepia.co.kr'; // 이메일
    $esim_day = $row['esim_day'];
    $nation_cd = 'GHA'; //국적코드
    $buy_user_name = $row['buy_user_name'];
    $shop_no = $row['shop'];
    $passport = $row['passport'];
    $buy_user_email = $row['buy_user_email'];
    $paymentDT = date('YmdHis');
    // 쇼핑몰별 추천인ID
    if($shop_no == 11){
        $rcmndr_id = "1313042946";
    }else{
        $rcmndr_id = "1313033433";
    }
    $total_cnt = '1'; // 총 개수
    $order_ymd = date('Ymd');

    // 상품 코드 매핑
    $codeMap = [
        "1" => "NA00007679",
        "2" => "NA00007679",
        "3" => "NA00008249",
        "4" => "NA00008814",
        "5" => "NA00008250",
        "6" => "NA00008815",
        "7" => "NA00008777",
        "8" => "NA00008816",
        "9" => "NA00008817",
        "10" => "NA00008251",
        "15" => "NA00008778",
        "20" => "NA00008252",
        "30" => "NA00008253",
        "60" => "NA00008779",
        "90" => "NA00008780"
    ];
    // eSIM 일수에 따른 상품 코드 결정
    $product_code = isset($codeMap[$esim_day]) ? $codeMap[$esim_day] : null;
    $esimEmailOK = 0; //이메일 양식 확인

    // 이메일 양식 확인
    if (filter_var($buy_user_email, FILTER_VALIDATE_EMAIL)) {
        $esimEmailOK = 1; //이메일 양식 확인
    }

    // api 6 요청 데이터 생성
    $sk_api6_data = json_encode([
        "company" => $company,
        "apiType" => $sk_api6_type,
        "roming_typ_cd" => $roming_typ_cd,
        "post_sale_org_id" => $post_sale_org_id,
        "rental_fee_prod_id" => $product_code
    ], JSON_UNESCAPED_UNICODE);


    // SK API6 요청
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sk_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' ) );
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $sk_api6_data);

    $sk_api6_res = curl_exec($ch);
    curl_close($ch);
    $sk_api6_response = json_decode($sk_api6_res, true);

    log_write($order_item_code, $sk_api6_data, $api6_log_request_file_name, $api6_log_request_path);

    // SK API6 응답 확인 및 오류 처리
    if (!$sk_api6_response || !isset($sk_api6_response["RSV_EQP_CNT"])) {
        log_write($order_item_code, $sk_api6_res,
            $api6_log_error_file_name, $api6_log_error_path);

        $failed[] = $order_item_code;   // 실패 목록 기록
        continue;                       // 다음 레코드로
    }

    // API 리턴값 로그 및 오류 확인
    log_write($order_item_code, $sk_api6_response, $api6_log_response_file_name, $api6_log_response_path);

    $rsv_eqp_cnt = intval($sk_api6_response["RSV_EQP_CNT"]);

    // api7 요청 데이터 생성
    $sk_api7_data = json_encode([
        "company" => $company,
        "apiType" => $sk_api7_type,
        "RENTAL_SCHD_STA_DTM" => $end_date,
        "RENTAL_SCHD_END_DTM" => $end_date,
        "RENTAL_SALE_ORG_ID" => $post_sale_org_id,
        "DOM_CNTC_NUM" => $dom_cntc_num,
        "EMAIL_ADDR" => $email,
        "RSV_RCV_DTM" => $paymentDT,
        "ROMING_PASSPORT_NUM" => $order_item_code,
        "CUST_NM" => $buy_user_name,
        "NATION_CD" => $nation_cd,
        "RCMNDR_ID" => $rcmndr_id,
        "TOTAL_CNT" => $total_cnt,
        "IN1" => [
            [
                "RSV_VOU_NUM" => $order_item_code,
                "ROMING_TYP_CD" => $roming_typ_cd,
                "RENTAL_FEE_PROD_ID" => $product_code
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);


    // API7 요청 데이터 로그
    log_write($order_item_code, $sk_api7_data,$api7_log_request_file_name, $api7_log_request_path);

    // SK API 전송 데이터
    $query = "INSERT INTO sk_api_send (order_item_code, rental_schd_sta_dtm, rental_schd_end_dtm, rental_sale_org_id, dom_cntc_num, email_addr, rsv_rcv_dtm, roming_passport_num, cust_nm, nation_cd, rcmndr_id, total_cnt, roming_typ_cd, rental_fee_prod_id) 
						VALUES ('$order_item_code','$end_date','$end_date','$post_sale_org_id','$dom_cntc_num','$email','$paymentDT','$order_item_code','$buy_user_name','$nation_cd','$rcmndr_id','$total_cnt','$roming_typ_cd','$product_code')";

    $result = mysqli_query($db_conn, $query);
    if (!$result) {
        echo "[DB ERROR] $query \n" . mysqli_error($db_conn) . " \n";
    }


    // 단말기 가용 수량이 1개 이상일 때만 API7 요청
    if ($rsv_eqp_cnt > 1) {

        // ✅ API7 요청
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sk_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' ) );
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $sk_api7_data);

        $apiDTS = date("Y-m-d H:i:s");
        $sk_api7_res = curl_exec($ch);
        if ($sk_api7_res) {
            $apiDTR = date("Y-m-d H:i:s");
        }
        curl_close($ch);
        $sk_api7_response = json_decode($sk_api7_res, true);
        log_write($order_item_code, $sk_api7_response, $api7_log_response_file_name, $api7_log_response_path);

// ✅ API7 응답 확인 및 오류 처리
        /* ---------- API7 OUT1 검사 ---------- */
        if (!$sk_api7_response || !isset($sk_api7_response['OUT1'])) {
            log_write($order_item_code, $sk_api7_res,
                $api7_log_error_file_name, $api7_log_error_path);

            $failed[] = $order_item_code;
            continue;
        }



        $order_ymd = date("Ymd");
        // API 리턴값 오류 확인
        if (!$sk_api7_response['OUT1']) {
            $emailState = 0;
            $note = $sk_api7_res; //오류 메세지
            $query = "INSERT INTO tb_esim_order_item_sk_red (order_id, order_item_code, shop, send_api_time, return_api_time, buy_user_name, passport, esim_day, email_state, buy_user_email, note, order_ymd) 
								VALUES ('$order_item_code','$order_item_code','$shop_no','$apiDTS','$apiDTR','$buy_user_name','$order_item_code','$esim_day',$emailState,'$buy_user_email','$note',$order_ymd)";

            $result = mysqli_query($db_conn, $query);
            if (!$result) {
                echo "[DB ERROR] $query \n" . mysqli_error($db_conn) . " \n";
            }
            $failed[] = $order_item_code; // 실패 목록 기록
            continue; // 다음 레코드로
        }


        // API7 OUT1 데이터 추출
        $api7_return_data = $sk_api7_response['OUT1'];

        $rental_mst_num = $api7_return_data[0]['RENTAL_MST_NUM'];
        $eqp_mdl_cd = $api7_return_data[0]['EQP_MDL_CD'];
        $esim_mapping_id = $api7_return_data[0]['ESIM_MAPPING_ID'];
        $eqp_ser_num = $api7_return_data[0]['EQP_SER_NUM'];
        $roming_phon_num = $api7_return_data[0]['ROMING_PHON_NUM'];
        $roming_num = $api7_return_data[0]['ROMING_NUM'];

        $total_cnt = $sk_api7_response['TOTAL_CNT'];
        $rental_mgmt_num = $sk_api7_response['RENTAL_MGMT_NUM'];


        // 리턴 데이터
        $query = "INSERT INTO sk_api_return (order_item_code, rental_mst_num, eqp_mdl_cd, esim_mapping_id, eqp_ser_num, roming_phon_num, roming_num, total_cnt, rental_mgmt_num) 
							VALUES ('$order_item_code','$rental_mst_num','$eqp_mdl_cd','$esim_mapping_id','$eqp_ser_num','$roming_phon_num','$roming_num','$total_cnt','$rental_mgmt_num')";

        $result = mysqli_query($db_conn, $query);
        if (!$result) {
            echo "[DB ERROR] $query \n" . mysqli_error($db_conn) . " \n";
        }


        $qr_arr = explode("$", $esim_mapping_id);
        $smdp = $qr_arr[0] . '$' . $qr_arr[1];
        $varQRCodePath = $qr_arr[2];

        /************************
         * 이메일 발송
         *************************/

        if ($esimEmailOK == 1) { // 이메일 양식이 맞을 경우

            if ($varQRCodePath) {

                ob_start();
                QRcode::png($esim_mapping_id, "/var/www/html/mobile_app/api/sk_qrcode/$varQRCodePath.png", "L", 12, 2);
                ob_end_clean();
                $varQRCodeImg = "https://www.koreaesim.com/mobile_app/api/sk_qrcode/$varQRCodePath.png";

                // eSIM 이용 기간 1일이거나 국문몰($shop_no==1)일 경우 번호 제공되지 않음
                /**** 번호 표시 수정
                 *
                 * if($esimDays == 1){
                 * $varCtn = 'Not Provided';
                 * }else{
                 * $varCtn = $roming_phon_num;
                 * }
                 ****/

                if ($esim_day !== "1" && !empty($rental_mgmt_num)) {
                    $rentalMgmtNum = $rental_mgmt_num;
                } else {
                    $rentalMgmtNum = 'X';
                }

// Set $varCtn based on $esimDays
                $varCtn = ($esim_day !== "1") ? $roming_phon_num : 'X';


                if($shop_no === "2"){
                    include "/var/www/html/mobile_app/mgr/email_contents/email_contents_sk_cn.php";
                } else if($shop_no === "4" || $shop_no === "15"){
                    include "/var/www/html/mobile_app/mgr/email_contents/email_contents_sk_jp.php";
                }
                else {
                    include "/var/www/html/mobile_app/mgr/email_contents/email_contents_sk.php";
                }



                $result = mail($buy_user_email, $email_subject, $email_contents, $email_headers);

                $emailDTS = date("Y-m-d H:i:s");


                if ($result) {
                    //메일 전송 성공
                    $emailState = 2;
                    $query = "INSERT INTO tb_esim_order_item_sk_red (order_id, order_item_code, shop, send_api_time, return_api_time, buy_user_name, passport, esim_day, email_state, send_email_time, buy_user_email, payment_date, ctn, qr_code, smdp_address, activation_code, order_ymd) 
                    VALUES ('$order_item_code','$order_item_code','$shop_no','$apiDTS','$apiDTR','$buy_user_name','$order_item_code','$esim_day',$emailState,'$emailDTS','$buy_user_email','$paymentDT','$roming_phon_num','$esim_mapping_id','$smdp','$varQRCodePath', $order_ymd)";

                    $success++;

                } else {
                    // 메일 전송 실패
                    $emailState = 0;
                    $query = "INSERT INTO tb_esim_order_item_sk_red (order_id, order_item_code, shop, send_api_time, return_api_time, buy_user_name, passport, esim_day, email_state, send_email_time, buy_user_email, payment_date, ctn, qr_code, smdp_address, activation_code, order_ymd) 
                    VALUES ('$order_item_code','$order_item_code','$shop_no','$apiDTS','$apiDTR','$buy_user_name','$order_item_code','$esim_day',$emailState,'$emailDTS','$buy_user_email','$paymentDT','$roming_phon_num','$esim_mapping_id','$smdp','$varQRCodePath', $order_ymd)";

                    $failed[] = $order_item_code;
                }

                $result = mysqli_query($db_conn, $query);
                if (!$result) {
                    echo "[DB ERROR] $query \n" . mysqli_error($db_conn) . " \n";
                }


            } //if($varQRCodePath)

        } else { // 이메일 양식이 틀릴 경우

            $emailState = 0;
            $note = "이메일 양식 오류";
            $query = "INSERT INTO tb_esim_order_item_sk_red (order_id, order_item_code, shop, send_api_time, return_api_time, buy_user_name, passport, esim_day, email_state, buy_user_email, payment_date, note, ctn, qr_code, smdp_address, activation_code, order_ymd) 
            VALUES ('$order_item_code','$order_item_code','$shop_no','$apiDTS','$apiDTR','$buy_user_name','$order_item_code','$esim_day',$emailState,'$buy_user_email','$paymentDT','$note','$roming_phon_num','$esim_mapping_id','$smdp','$varQRCodePath', $order_ymd)";

            $result = mysqli_query($db_conn, $query);
            if (!$result) {
                echo "[DB ERROR] $query \n" . mysqli_error($db_conn) . " \n";
            }

            $failed[] = $order_item_code;
            continue; // 다음 레코드로
        }
    } else {  //if($resData['RSV_EQP_CNT'] < 1) 단말기 가용 수량이 부족 할 때

        $emailState = 0;
        $note = $sk_api6_res; //오류 메세지
        $query = "INSERT INTO tb_esim_order_item_sk_red (order_id, order_item_code, shop, send_api_time, return_api_time, buy_user_name, passport, esim_day, email_state, buy_user_email, note, order_ymd) 
        VALUES ('$order_item_code','$order_item_code','$shop_no','$apiDTS','$apiDTR','$buy_user_name','$order_item_code','$esim_day',$emailState,'$buy_user_email','$note',$order_ymd)";

        $result = mysqli_query($db_conn, $query);
        if (!$result) {
            echo "[DB ERROR] $query \n" . mysqli_error($db_conn) . " \n";
        }

        /* ---- 여기 두 줄이 핵심 ---- */
        $failed[] = $order_item_code;   // 실패로 집계
        continue;                      // 다음 주문 처리
    } //if($resData['RSV_EQP_CNT'] > 1)
}

// 데이터베이스 연결 종료
mysqli_close($db_conn);

// 모든 로직이 정상적으로 완료되었을 때 아래 코드 실행
if ($failed) {
    $cnt = count($failed);
    $list = implode(', ', $failed);
    echo "<script>
        alert('API 완료 – 성공 {$success}건 / 실패 {$cnt}건 (코드: {$list})');
        location.href='ktmsim_esim_order_email_skt_red.php';
    </script>";
} else {
    echo "<script>
        alert('API 완료 – 전부 성공 {$success}건');
        location.href='ktmsim_esim_order_email_skt_red.php';
    </script>";
}
exit;
?>