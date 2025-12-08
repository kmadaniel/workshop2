<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 40px;
            background: #f5f5f5;
        }

        h1 {
            text-align: center;
            font-size: 40px;
            margin-bottom: 40px;
            color: #333;
        }

        .news-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            padding: 0 40px;
        }

        .news-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .news-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .news-content {
            padding: 20px;
        }

        .news-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .news-desc {
            font-size: 15px;
            color: #555;
            margin-bottom: 20px;
        }

        .read-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #7b3ff3;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
        }

        .read-btn:hover {
            background: #6527d1;
        }
    </style>
</head>
<body>

    <h1>Latest News</h1>

    <div class="news-container">

        <!-- NEWS 1 -->
        <div class="news-card">
            <img src="flood.jpg" alt="Banjir">
            <div class="news-content">
                <div class="news-title">Banjir Kilat Melanda Kuala Lumpur</div>
                <div class="news-desc">Hujan lebat selama 3 jam menyebabkan beberapa kawasan rendah dinaiki air.</div>
                <a href="#" class="read-btn">Read More</a>
            </div>
        </div>

        <!-- NEWS 2 -->
        <div class="news-card">
            <img src="cleanup.jpg" alt="Cleanup">
            <div class="news-content">
                <div class="news-title">Program Kutip Sampah Bersama Komuniti</div>
                <div class="news-desc">Sukarelawan berkumpul bagi membersihkan sungai di kawasan Ampang.</div>
                <a href="#" class="read-btn">Read More</a>
            </div>
        </div>

        <!-- NEWS 3 -->
        <div class="news-card">
            <img src="fire.jpg" alt="Fire">
            <div class="news-content">
                <div class="news-title">Kebakaran di Setapak Terkawal</div>
                <div class="news-desc">Pihak bomba berjaya mengawal api dari merebak ke bangunan lain.</div>
                <a href="#" class="read-btn">Read More</a>
            </div>
        </div>

    </div>

</body>
</html>
