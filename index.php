<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 로그 파일 설정
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// 메모리 제한 늘리기
ini_set('memory_limit', '512M');

// 실행 시간 제한 늘리기
set_time_limit(300);
ini_set('max_execution_time', 300);
ini_set('default_socket_timeout', 300);

// API 키 설정
define('OPENAI_API_KEY', 'api key');

class NewsDigest {
    private $article;
    private $api_key;
    
    public function __construct($content = null, $isUrl = false, $keyword = null) {
        // CURL 타임아웃 설정도 늘립니다
        ini_set('default_socket_timeout', 180); // 3분으로 설정
        
        $this->api_key = OPENAI_API_KEY;
        if ($keyword) {
            $this->article = $this->getTopArticlesByKeyword($keyword);
        } else if ($isUrl) {
            $this->article = $this->getArticleFromUrl($content);
        } else {
            $this->article = $content;
        }
    }
    
    // URL에서 기사 내용 가져오기
    private function getArticleFromUrl($url) {
        try {
            // CURL 초기화
            $curl = curl_init($url);
            
            // CURL 옵션 설정
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,  // 리다이렉트 따라가기
                CURLOPT_SSL_VERIFYPEER => false, // SSL 검증 비활성화
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                CURLOPT_TIMEOUT => 300,  // CURL 타임아웃도 3분으로 늘립니다
                CURLOPT_CONNECTTIMEOUT => 60  // 연결 타임아웃은 1분으로 설정
            ]);

            // URL 가져오기
            $html = curl_exec($curl);
            
            if ($html === false) {
                $error = curl_error($curl);
                curl_close($curl);
                return "URL에서 내용을 가져올 수 없어요 😢: " . $error;
            }
            
            curl_close($curl);

            // 네이버 뉴스 본문 추출 (id="dic_area" 영역)
            if (strpos($url, 'news.naver.com') !== false) {
                preg_match('/<article id="dic_area"[^>]*>(.*?)<\/article>/s', $html, $matches);
                if (!empty($matches[1])) {
                    $text = $matches[1];
                } else {
                    // 본문을 찾지 못한 경우 전체 HTML에서 추출
                    $text = $html;
                }
            } else {
                $text = $html;
            }

            // HTML 태그 제거 및 텍스트 정리
            $text = strip_tags($text);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);
            
            // 텍스트가 너무 길면 적당히 자르기
            if (mb_strlen($text) > 3000) {
                $text = mb_substr($text, 0, 3000) . '...';
            }

            return $text;
            
        } catch (Exception $e) {
            return "URL 처리 중 오류가 발생했어요: " . $e->getMessage();
        }
    }
    
    // 키워드 기반 인기 뉴스 가져오기
    private function getTopArticlesByKeyword($keyword) {
        $articles = [];
        
        // 네이버 뉴스 검색 URL
        $searchUrl = "https://search.naver.com/search.naver?where=news&query=" . urlencode($keyword) . "&sort=like";
        
        try {
            $curl = curl_init($searchUrl);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_TIMEOUT => 30
            ]);
            
            $html = curl_exec($curl);
            curl_close($curl);
            
            // 뉴스 링크 추출 (정규식 패턴)
            preg_match_all('/<a href="(https:\/\/n.news.naver.com\/article\/[^"]+)"/', $html, $matches);
            
            // 상위 3개 기사 가져오기
            $count = 0;
            $uniqueUrls = array_unique($matches[1]);
            
            foreach ($uniqueUrls as $url) {
                if ($count >= 3) break;
                
                $articleContent = $this->getArticleFromUrl($url);
                if (!empty($articleContent)) {
                    $articles[] = $articleContent;
                    $count++;
                }
            }
            
            // 모든 기사 내용을 하나로 합치기
            return implode("\n\n=== 다음 기사 ===\n\n", $articles);
            
        } catch (Exception $e) {
            return "인기 뉴스를 가져오는 중 오류가 발생했어요: " . $e->getMessage();
        }
    }
    
    public function getMZSummary($style = 'medium') {
        $summary = $this->getGPT4Summary($style);
        return $this->convertToStyle($summary, $style);
    }
    
    private function getGPT4Summary($style = 'mild') {
        $systemMessages = [
            'mild' => '당신은 10대 후반~20대 MZ세대를 위한 뉴스 요약 전문가입니다.
- "~인 것 같아요", "~라고 해요" 같은 부드러운 표현 사용
- 이모티콘과 이모지를 적절히 활용 (, 👀, ✨, 🔥)
- "ㅋㅋ", "ㄹㅇ", "ㄱㅇㄷ" 같은 줄임말 사용
- "완전", "대박", "핵심이 뭐냐면" 같은 구어체 사용',

            'spicy' => '당신은 DC인사이드 갤러리 스타일의 뉴스 요약 전문가입니다.
- "ㅇㅇ", "ㄹㅇㅋㅋ", "ㅈㄱㄴ" 같은 극단적 줄임말 사용
- "~노", "~다이가", "~ㅅㅂ" 같은 비격식 표현 사용
- 과격하고 직설적인 표현 사용 (단, 혐오나 차별적 표현은 제외)
- 어려운 용어는 비꼬거나 조롱하는 듯한 설명
- 모든 문장을 최대한 짧게, 파편적으로 작성'
        ];

        $data = [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemMessages[$style] . '

1. 요약 규칙:
- 뉴스의 핵심 내용을 3-4개의 포인트로 추출
- 각 포인트는 2-3문장으로 짧게 설명
- 어려운 용어는 쉽게 풀어서 설명

2. 형식:
[SUMMARY]
각 포인트는 줄바꿈으로 구분하여 작성

[TERMS]
뉴스에서 발견된 어려운 용어 3-4개를 선택하여 설명
- 용어1: 쉽게 설명
- 용어2: 쉽게 설명
- 용어3: 쉽게 설명

[REACTIONS]
이 뉴스에 대한 예상되는 댓글이나 반응 4개를 작성
(뉴스 내용과 관련된 구체적인 반응을 작성해주세요)

[TAGS]
관련 해시태그 3-4개'
                ],
                [
                    'role' => 'user',
                    'content' => $this->article
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 800
        ];

        try {
            $curl = curl_init('https://api.openai.com/v1/chat/completions');
            
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($jsonData === false) {
                throw new Exception("JSON 인코딩 실패: " . json_last_error_msg());
            }
            
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->api_key
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 180
            ]);

            $response = curl_exec($curl);
            
            if ($response === false) {
                throw new Exception("CURL 오류: " . curl_error($curl));
            }
            
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            $result = json_decode($response, true);
            
            if ($httpCode !== 200) {
                error_log("API 오류 응답: " . $response);
                throw new Exception("API 오류 (HTTP $httpCode): " . 
                    ($result['error']['message'] ?? '알 수 없는 오류가 발생했어요'));
            }
            
            return $result['choices'][0]['message']['content'] ?? "요약에 실패했어요 😢";
            
        } catch (Exception $e) {
            error_log("GPT 요약 오류: " . $e->getMessage());
            return "요약 중 오류가 발생했어요 😢: " . $e->getMessage();
        }
    }
    
    private function convertToStyle($summary, $style) {
        $sections = [
            'summary' => $summary,
            'terms' => '',
            'reactions' => '',  // 반응 섹션 추가
            'tags' => ''
        ];

        if (strpos($summary, '[SUMMARY]') !== false) {
            preg_match('/\[SUMMARY\](.*?)(?=\[TERMS\])/s', $summary, $summaryMatch);
            preg_match('/\[TERMS\](.*?)(?=\[REACTIONS\])/s', $summary, $termsMatch);
            preg_match('/\[REACTIONS\](.*?)(?=\[TAGS\])/s', $summary, $reactionsMatch);  // 반응 매칭 추가
            preg_match('/\[TAGS\](.*?)$/s', $summary, $tagsMatch);

            if (!empty($summaryMatch[1])) $sections['summary'] = trim($summaryMatch[1]);
            if (!empty($termsMatch[1])) $sections['terms'] = trim($termsMatch[1]);
            if (!empty($reactionsMatch[1])) $sections['reactions'] = trim($reactionsMatch[1]);  // 반응 저장
            if (!empty($tagsMatch[1])) $sections['tags'] = trim($tagsMatch[1]);
        }

        $styleFormats = [
            'mild' => [
                'intro' => "📰 오늘의 1분 뉴스 요약!\n\n",
                'bullets' => ["📌 진짜 중요한 거 알려드림 >> ", "🔥 핫한 이슈 체크! ", "👀 놓치면 안 되는 포인트! "],
                'terms_intro' => "\n📚 뉴스 속 어려운 용어 설명!\n\n",
                'term_bullet' => "💫 "
            ],
            'spicy' => [
                'intro' => "ㅇㅎ 뉴스 요약한다 ㄱㄱ\n\n",
                'bullets' => ["ㅇㅇ ", "근데 ", "그리고 "],
                'terms_intro' => "\nㅇㅇ 모르는 단어 설명해준다\n\n",
                'term_bullet' => ">> "
            ]
        ];

        $format = $styleFormats[$style];
        $styledSummary = $format['intro'];

        $points = explode("\n", trim($sections['summary']));
        foreach ($points as $index => $point) {
            if (!empty(trim($point))) {
                $bullet = $format['bullets'][$index % count($format['bullets'])];
                $styledSummary .= $bullet . trim($point) . "\n\n";
            }
        }

        if (!empty($sections['terms'])) {
            $styledSummary .= $format['terms_intro'];
            $terms = explode("\n", trim($sections['terms']));
            foreach ($terms as $term) {
                if (!empty(trim($term))) {
                    $styledSummary .= $format['term_bullet'] . trim($term) . "\n";
                }
            }
            $styledSummary .= "\n";
        }

        // 반응 섹션 추가
        if (!empty($sections['reactions'])) {
            $styledSummary .= "\n💬 예상되는 반응\n\n";
            $reactions = explode("\n", trim($sections['reactions']));
            foreach ($reactions as $reaction) {
                if (!empty(trim($reaction))) {
                    $styledSummary .= ($style === 'mild' ? "💭 " : "ㅇㅇ ") . trim($reaction) . "\n";
                }
            }
            $styledSummary .= "\n";
        }

        if (!empty($sections['tags'])) {
            $styledSummary .= implode(" ", array_slice(explode(" ", trim($sections['tags'])), 0, 4));
        }

        return $styledSummary;
    }

    // NewsDigest 클래스에 새로운 메서드 추가
    public function getTopNews($style = 'medium') {
        $articles = [];
        
        // 네이버 메인 뉴스 랭킹 페이지 - 실시간 인기뉴스로 변경
        $rankingUrl = "https://news.naver.com/main/ranking/popularMemo.naver";
        
        try {
            $curl = curl_init($rankingUrl);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_TIMEOUT => 30
            ]);
            
            $html = curl_exec($curl);
            curl_close($curl);
            
            // 인기뉴스 링크 추출 패턴 수정
            preg_match_all('/<a href="(https:\/\/n.news.naver.com\/article\/[^"]+)" class="list_title[^"]*"[^>]*>([^<]+)<\/a>/i', $html, $matches);
            
            // 상위 3개 기사 처리
            for ($i = 0; $i < 3 && $i < count($matches[1]); $i++) {
                $url = $matches[1][$i];
                $articleContent = $this->getArticleFromUrl($url);
                
                if (!empty($articleContent)) {
                    $this->article = $articleContent;
                    $summary = $this->getMZSummary($style);
                    
                    $articles[] = [
                        'summary' => $summary,
                        'url' => $url
                    ];
                }
            }
            
            return $articles;
            
        } catch (Exception $e) {
            return "인기 뉴스를 가져오는 중 오류가 발생했어요: " . $e->getMessage();
        }
    }
}

// POST 요청 처리 부분 수정
$summaries = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $style = $_POST['style'] ?? 'mild';
    
    if (isset($_POST['top_news'])) {
        $digest = new NewsDigest();
        $summaries = $digest->getTopNews($style);
    } elseif (!empty($_POST['article'])) {
        $digest = new NewsDigest($_POST['article'], false);
        $summary = $digest->getMZSummary($style);
    } elseif (!empty($_POST['url'])) {
        $digest = new NewsDigest($_POST['url'], true);
        $summary = $digest->getMZSummary($style);
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MZ뉴스 요약이 1분컷 ⚡️</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Apple SD Gothic Neo', sans-serif;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 600px;
        }

        h1 {
            color: #333;
            text-align: center;
            font-size: 1.8rem;
            margin-bottom: 25px;
            line-height: 1.4;
        }

        textarea {
            width: 100%;
            min-height: 180px;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 12px;
            margin-bottom: 15px;
            font-size: 1rem;
            resize: vertical;
            transition: border-color 0.3s;
        }

        textarea:focus {
            outline: none;
            border-color: #FF3366;
        }

        button {
            background: #FF3366;
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 30px;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
            transition: transform 0.2s, background 0.3s;
        }

        button:hover {
            background: #FF1744;
            transform: translateY(-2px);
        }

        .result {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            white-space: pre-line;
            font-size: 1rem;
            line-height: 1.6;
            border: 1px solid #eee;
        }

        .hashtags {
            color: #FF3366;
            font-weight: bold;
        }

        /* 모바일 대응 */
        @media (max-width: 480px) {
            .container {
                padding: 20px;
            }

            h1 {
                font-size: 1.5rem;
            }

            textarea {
                min-height: 150px;
            }

            button {
                font-size: 1rem;
                padding: 12px 20px;
            }
        }

        .input-group {
            margin-bottom: 15px;
        }

        .url-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .url-input:focus {
            outline: none;
            border-color: #FF3366;
        }

        .separator {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }

        .separator::before,
        .separator::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background-color: #ddd;
        }

        .separator::before {
            left: 0;
        }

        .separator::after {
            right: 0;
        }

        .separator span {
            background-color: white;
            padding: 0 10px;
            color: #666;
            font-size: 0.9rem;
        }

        /* 기존 스타일에 추가 */
        .terms-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }

        .term-item {
            margin: 10px 0;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 8px;
        }

        .style-selector {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }

        .style-selector input[type="radio"] {
            display: none;
        }

        .style-selector label {
            padding: 10px 20px;
            background: #f0f0f0;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: bold;
        }

        .style-selector input[type="radio"]:checked + label {
            background: #FF3366;
            color: white;
        }

        /* 매운맛 선택시 특별 효과 */
        .style-selector input[type="radio"]#style-spicy:checked + label {
            background: #FF0000;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📰 MZ뉴스 요약이 1분컷 ⚡️</h1>
        
        <form method="POST">
            <div class="style-selector">
                <input type="radio" id="style-mild" name="style" value="mild" checked>
                <label for="style-mild">순한맛 🔥</label>
                
                <input type="radio" id="style-spicy" name="style" value="spicy">
                <label for="style-spicy">매운맛 🌶️</label>
            </div>

            <button type="submit" name="top_news" value="1" class="top-news-btn">
                🔥 실시간 인기뉴스 TOP 3 보기
            </button>
            
            <div class="separator">
                <span>또는</span>
            </div>

            <div class="input-group">
                <input type="url" name="url" placeholder="뉴스 기사 URL을 붙여넣어주세요!" 
                       value="<?= isset($_POST['url']) ? htmlspecialchars($_POST['url']) : '' ?>"
                       class="url-input">
            </div>
            
            <div class="separator">
                <span>또는</span>
            </div>

            <textarea name="article" placeholder="여기에 뉴스 기사를 붙여넣어주세요!"><?= isset($_POST['article']) ? htmlspecialchars($_POST['article']) : '' ?></textarea>
            <button type="submit">요약하기 🚀</button>
        </form>

        <?php if (!empty($summaries)): ?>
            <?php foreach ($summaries as $index => $item): ?>
                <div class="result">
                    <h2 class="news-number">🏆 실시간 인기뉴스 <?= $index + 1 ?>위</h2>
                    <div class="news-content">
                        <?= nl2br(htmlspecialchars($item['summary'])) ?>
                    </div>
                    <div class="news-link">
                        <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank">
                            원문 보기 👉
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif (!empty($summary)): ?>
            <div class="result">
                <?= nl2br(htmlspecialchars($summary)) ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
