<?php

require_once __DIR__ . '/../includes/config.php';

// 検索条件
$keyword  = trim($_GET['keyword'] ?? '');
$category = trim($_GET['category'] ?? '');

// ページ番号
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
// 並び替え：ORDER BYの列名・方向はプレースホルダにできないため、
// 必ずホワイトリスト（許可リスト）に照合してから使う。
$sortOptions = [
    'new'        => 'created_at DESC',
    'price_asc'  => 'price ASC',
    'price_desc' => 'price DESC',
];
$sort    = $_GET['sort'] ?? 'new';
$orderBy = $sortOptions[$sort] ?? $sortOptions['new'];

// WHERE句作成
$conditions = [];
$params = [];

if ($keyword !== '') {
    $conditions[] = 'name LIKE :keyword';
    $params['keyword'] = '%' . $keyword . '%';
}

if ($category !== '') {
    $conditions[] = 'category = :category';
    $params['category'] = $category;
}

$whereSql = '';

if (!empty($conditions)) {
    $whereSql = 'WHERE ' . implode(' AND ', $conditions);
}

// 総件数取得
$countSql = "
    SELECT COUNT(*)
    FROM items
    {$whereSql}
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);

$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// 商品取得
$sql = "
    SELECT
        id,
        name,
        price,
        image,
        stock,
        category
    FROM items
    {$whereSql}
     ORDER BY {$orderBy}
    LIMIT {$perPage}
    OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// カテゴリ一覧
$categories = $pdo
    ->query("
        SELECT DISTINCT category
        FROM items
        WHERE category IS NOT NULL
        ORDER BY category
    ")
    ->fetchAll(PDO::FETCH_COLUMN);

// 検索条件保持
function buildQuery(array $overrides = []): string
{
    $base = [
        'keyword' => $_GET['keyword'] ?? '',
        'category' => $_GET['category'] ?? '',
        'page' => $_GET['page'] ?? 1,
    ];

    return http_build_query(array_merge($base, $overrides));
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>商品一覧 ― Tsuchi-to-Hi</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header>
    <a href="index.php">Tsuchi-to-Hi</a>
</header>

<main>

    <h1>商品一覧</h1>

    <form class="search-form" method="get" action="items.php">

        <input
            type="text"
            name="keyword"
            placeholder="商品名で検索"
            value="<?= htmlspecialchars($keyword) ?>"
        >

        <select name="category">
            <option value="">すべてのカテゴリ</option>

            <?php foreach ($categories as $c): ?>
                <option
                    value="<?= htmlspecialchars($c) ?>"
                    <?= $c === $category ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($c) ?>
                </option>
            <?php endforeach; ?>

        </select>
          <select name="sort">
                <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>新着順</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>価格が安い順</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>価格が高い順</option>
            </select>   
        <button type="submit">検索</button>

    </form>
                <p>
                    totalCount = <?= $totalCount ?><br>
                    totalPages = <?= $totalPages ?><br>
                    page = <?= $page ?>
                </p>
    <?php if (empty($items)): ?>

        <p class="empty-state">
            該当する商品が見つかりませんでした。
        </p>

    <?php else: ?>

        <div class="item-grid">

            <?php foreach ($items as $item): ?>

                <a
                    class="item-card"
                    href="item.php?id=<?= (int)$item['id'] ?>"
                    style="text-decoration:none;color:inherit;"
                >

                    <img
                        src="<?= !empty($item['image'])
                            ? 'uploads/items/' . htmlspecialchars($item['image'])
                            : 'https://placehold.co/220x140' ?>"
                        alt=""
                    >

                    <div class="name">
                        <?= htmlspecialchars($item['name']) ?>
                    </div>

                    <div class="price">
                        ¥<?= number_format($item['price']) ?>
                    </div>

                </a>

            <?php endforeach; ?>

        </div>

        <?php if ($totalPages > 1): ?>

        <div class="pagination">

            <?php if ($page > 1): ?>
                <a href="items.php?<?= buildQuery(['page' => $page - 1]) ?>">
                    ‹ 前へ
                </a>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++): ?>

                <?php if ($p == $page): ?>

                    <span class="current">
                        <?= $p ?>
                    </span>

                <?php else: ?>

                    <a href="items.php?<?= buildQuery(['page' => $p]) ?>">
                        <?= $p ?>
                    </a>

                <?php endif; ?>

            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="items.php?<?= buildQuery(['page' => $page + 1]) ?>">
                    次へ ›
                </a>
            <?php endif; ?>

        </div>

        <?php endif; ?>

    <?php endif; ?>

</main>

</body>
</html>