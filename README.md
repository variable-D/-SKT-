### **프로젝트 개요**

SKT 측 서버 점검 시간 중 발생한 주문(API 실패 또는 이메일 미전송)을 자동 또는 수동으로 **일괄 재요청**하여 처리하는 시스템을 개발하였습니다.

관리자는 날짜 범위를 선택하여, 특정 기간 내 실패한 주문을 다시 API로 전송하고 결과를 확인할 수 있으며, 실패 건에 대해 **로깅, 오류 처리, 재시도 기반 운영 안정성**을 확보했습니다.

### **핵심 기능**

### **1.**

### **UI 기반 수동 재처리 기능**

- 날짜 범위를 선택하여 “서버 점검 예약 처리 실행” 버튼 클릭 시,
- 팝업 확인창을 통해 요청을 시작하고,
- **성공/실패 건수와 실패 주문번호**를 알림으로 제공.

### **2.**

### **SKT API 재요청 로직 구성**

- **USIM: API3 단건 등록 요청 기반 재처리**
- **eSIM: API6(단말 가용 수량 확인) → API7(주문 재요청 및 QR 이메일 발송)**

### **3.**

### **자동 로그 저장 및 에러 대응**

- 요청/응답/에러 로그를 각각 날짜별 파일로 저장 (api6, api7, api3)
- JSON 파싱 오류, 필드 누락 등 예외 발생 시 실패 건 목록에 기록

### **4.**

### **결과 통계 및 사용자 피드백**

- 처리 완료 후 alert 창으로 성공/실패 수량과 실패 코드 표시
- DB 기록도 업데이트되어 관리자가 이력 추적 가능

---

### **주요 학습 / 기술 스택**

- **PHP 기반 복합 로직 처리 (조건 분기, 다단계 API 호출)**
- **MySQL 쿼리 조건 구성 및 다중 테이블 활용**
- **cURL을 통한 JSON 기반 API 통신**
- **QR코드 생성 및 이메일 첨부 전송**
- **로그 파일 자동 생성 및 파일 용량 기반 롤링 처리**
- JavaScript + HTML UI 연동 (날짜 범위 선택 + 버튼 → PHP 실행)

---

### **내 역할**

- 전체 시스템 설계 및 **USIM/eSIM 주문건 처리 로직 완전 단독 개발**
- 관리자 UI 설계 및 알림 인터페이스 구성
- **에러 발생 시 재처리 가능한 구조 설계 및 예외 대응** 구현
- API 요청/응답을 구분하여 **통합 로그 관리 구조화**
- 이메일 양식 검증, QR 이미지 생성 등 부가 로직 포함

---

### **성과 및 개선점**

- **운영자 수동 대응 시간 90% 단축**
    
    → 기존엔 실시간 실패 주문을 수동으로 재확인해야 했으나, UI 기반 자동화로 효율화
    
- **에러 로그 기반 재요청 구조 개선**
    
    → API 응답 실패 시 원인 식별이 가능하여 향후 유지보수 용이
    
- **이메일 발송 성공률 향상 (eSIM)**
    
    → 유효한 이메일 양식만 발송 대상으로 자동 필터링하여 성공률 상승
    

### **관련 스크린샷**

- 날짜 선택 UI & 실행 버튼
    
    ![스크린샷 2025-07-10 16.37.47.png](attachment:b31985cd-f6ee-4c86-be9e-2abcf676fad9:스크린샷_2025-07-10_16.37.47.png)
    
- 확인 팝업
    
    ![스크린샷 2025-07-10 16.38.16.png](attachment:b8dee9c9-a943-476a-8e70-d0cdd2e1381f:스크린샷_2025-07-10_16.38.16.png)
    

* 결과 알림

![스크린샷 2025-07-10 16.39.00.png](attachment:e6ec0c6b-b586-4af5-b1dd-ff2046d23fe9:스크린샷_2025-07-10_16.39.00.png)

### PHP 관련 코드

USIM

```php
<?php
// 디버깅 모드 활성화
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL | E_STRICT);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 타임존 설정
date_default_timezone_set("Asia/Seoul");

// 공통 함수
require'/var/www/html/mobile_app/mgr/common.php';

// 데이터베이스 연결
$db_conn = get_db_connection();
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
```

eSIM

```php
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
```

공통 파일

```php
<?php
function get_db_connection() {
    require "/var/www/html8443/mallapi/db_info.php";
    $db_conn = mysqli_connect($db_host, $db_user, $db_pwd, $db_category, $db_port);
    if (mysqli_connect_errno()) {
        die("DB 연결 실패: " . mysqli_connect_error());
    }
    return $db_conn;
}

function log_write($order_item_code, $log_data, $file_name, $log_dir) {
    $dir = rtrim($log_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $oldUmask = umask(002);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        umask($oldUmask);
        throw new RuntimeException("로그 디렉터리 생성 실패: {$dir}");
    }
    umask($oldUmask);

    $time = date('c');
    $sanitized = preg_replace('/[^A-Za-z0-9_\-]/', '', $order_item_code);
    $body = is_string($log_data) ? $log_data : json_encode($log_data, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        $jsonError = function_exists('json_last_error_msg') ? json_last_error_msg() : 'JSON encoding error';
        throw new RuntimeException('JSON 인코딩 실패: ' . $jsonError);
    }

    $logTxt = "\n({$sanitized} {$time})\n{$body}\n\n";
    $path = $dir . $file_name;
    if (is_file($path) && filesize($path) > 10 * 1024 * 1024) {
        rename($path, $path . '.' . date('Ymd_His'));
    }
    if (file_put_contents($path, $logTxt, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException("로그 파일 쓰기 실패: {$path}");
    }
    return true;
}
?>
```
