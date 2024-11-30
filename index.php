<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = $_POST['recipient'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $content = $_POST['content'] ?? '';
    $tone = $_POST['tone'] ?? '';

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '당신은 전문적인 이메일 작성 도우미입니다. 주어진 정보를 바탕으로 적절한 이메일을 작성해주세요.'
                ],
                [
                    'role' => 'user',
                    'content' => "다음 조건으로 이메일을 작성해주세요:\n".
                                "받는 사람 유형: {$recipient}\n".
                                "이메일 목적: {$purpose}\n".
                                "주요 내용: {$content}\n".
                                "어조: {$tone}\n"
                ]
            ]
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer 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',
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        $generated_email = "에러가 발생했습니다: " . $err;
    } else {
        $response_data = json_decode($response, true);
        $generated_email = $response_data['choices'][0]['message']['content'] ?? '이메일 생성에 실패했습니다.';
    }
    
    header('Content-Type: application/json');
    echo json_encode(['email' => $generated_email]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이메일 작성 도우미 AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">이메일 작성 도우미 AI</h1>
            
            <form id="emailForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        받는 사람 유형
                    </label>
                    <select name="recipient" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option>상사</option>
                        <option>동료</option>
                        <option>고객</option>
                        <option>비즈니스 파트너</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        이메일 목적
                    </label>
                    <select name="purpose" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option>요청사항</option>
                        <option>보고</option>
                        <option>문의</option>
                        <option>감사인사</option>
                        <option>사과</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        주요 내용
                    </label>
                    <textarea 
                        name="content"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        rows="4"
                        placeholder="이메일에 포함할 주요 내용을 입력해주세요."
                    ></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        어조
                    </label>
                    <select name="tone" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option>공식적</option>
                        <option>친근한</option>
                        <option>전문적</option>
                        <option>겸손한</option>
                    </select>
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    이메일 생성하기
                </button>
            </form>

            <div class="mt-6 p-4 bg-gray-50 rounded-md">
                <h2 class="text-lg font-medium text-gray-800 mb-2">생성된 이메일</h2>
                <div id="generatedEmail" class="bg-white p-4 rounded-md border border-gray-200 whitespace-pre-wrap">
                    <p class="text-gray-600">여기에 AI가 생성한 이메일 내용이 표시됩니다.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#emailForm').on('submit', function(e) {
                e.preventDefault();
                
                const $submitButton = $(this).find('button[type="submit"]');
                $submitButton.prop('disabled', true).text('생성 중...');
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        $('#generatedEmail').html(response.email.replace(/\n/g, '<br>'));
                    },
                    error: function() {
                        $('#generatedEmail').html('이메일 생성 중 오류가 발생했습니다.');
                    },
                    complete: function() {
                        $submitButton.prop('disabled', false).text('이메일 생성하기');
                    }
                });
            });
        });
    </script>
</body>
</html>
