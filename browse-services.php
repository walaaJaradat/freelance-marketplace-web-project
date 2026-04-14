<?php
require_once __DIR__ . "/includes/init.php";

$Title = "Browse Services";
$activePage = "browse-services.php";

if (!isset($baseUrl) || (string)$baseUrl === "") {
  $baseUrl = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
  $baseUrl = ($baseUrl === "" ? "/" : $baseUrl . "/");
}

$categories = [
  "Web Development",
  "Graphic Design",
  "Writing & Translation",
  "Digital Marketing",
  "Video & Animation",
  "Music & Audio",
  "Business Consulting",
  "Tutoring & Education"
];

function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

function isExternalOrData($p): bool {
  $p = (string)$p;
  return (preg_match('#^https?://#i', $p) || strpos($p, "data:") === 0);
}

function mediaRel($path, string $filenameDir = ""): string {
  $p = trim((string)$path);
  if ($p === "" || strtolower($p) === "null") return "";

  $p = str_replace("\\", "/", $p);

  if (isExternalOrData($p)) return $p;

  if (($pos = stripos($p, "uploads/")) !== false) return ltrim(substr($p, $pos), "/");
  if (($pos = stripos($p, "assets/"))  !== false) return ltrim(substr($p, $pos), "/");

  if ($filenameDir !== "" && strpos($p, "/") === false) {
    return trim($filenameDir, "/") . "/" . ltrim($p, "/");
  }

  return ltrim($p, "/");
}

function pickExistingRel(array $rels): string {
  foreach ($rels as $r) {
    $r = ltrim((string)$r, "/");
    if ($r !== "" && is_file(__DIR__ . "/" . $r)) return $r;
  }
  return "";
}

function img(string $baseUrl, $dbPath, $fallbackRel, string $filenameDir = "", bool $swapProfileDirs = false): string {
  $rel = mediaRel($dbPath, $filenameDir);
  if ($rel === "") $rel = mediaRel($fallbackRel);

  if ($rel !== "" && isExternalOrData($rel)) return $rel;

  $rel = ltrim($rel, "/");

  if ($swapProfileDirs && $rel !== "" && !is_file(__DIR__ . "/" . $rel)) {
    if (strpos($rel, "uploads/profile-photo/") === 0) {
      $alt = "uploads/profiles/" . substr($rel, strlen("uploads/profile-photo/"));
      if (is_file(__DIR__ . "/" . $alt)) $rel = $alt;
    } elseif (strpos($rel, "uploads/profiles/") === 0) {
      $alt = "uploads/profile-photo/" . substr($rel, strlen("uploads/profiles/"));
      if (is_file(__DIR__ . "/" . $alt)) $rel = $alt;
    }
  }

  if ($rel !== "" && !is_file(__DIR__ . "/" . $rel)) {
    $fb = mediaRel($fallbackRel);
    if ($fb !== "" && isExternalOrData($fb)) return $fb;
    $fb = ltrim($fb, "/");
    if ($fb !== "") $rel = $fb;
  }

  return rtrim($baseUrl, "/") . "/" . ltrim($rel, "/");
}

$servicePlaceholderRel = pickExistingRel([
  "uploads/services/placeholder-service.png",
]);

if ($servicePlaceholderRel === "") {
  $servicePlaceholderRel = 'data:image/svg+xml;utf8,' . rawurlencode('
    <svg xmlns="http://www.w3.org/2000/svg" width="600" height="400">
      <rect width="600" height="400" fill="#F1F3F5"/>
      <rect x="40" y="40" width="520" height="320" rx="18" fill="#E9ECEF"/>
      <circle cx="220" cy="190" r="55" fill="#CED4DA"/>
      <path d="M160 305l90-85 65 55 95-110 90 140H160z" fill="#ADB5BD"/>
    </svg>
  ');
}

$userPlaceholderRel = pickExistingRel([
  "uploads/profile-photo/user-default.jpg",
  "uploads/profiles/user-default.jpg",
]);

if ($userPlaceholderRel === "") {
  $userPlaceholderRel = 'data:image/svg+xml;utf8,' . rawurlencode('
    <svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">
      <rect width="120" height="120" rx="60" fill="#E9ECEF"/>
      <circle cx="60" cy="48" r="22" fill="#ADB5BD"/>
      <path d="M20 110c7-22 26-34 40-34s33 12 40 34" fill="#ADB5BD"/>
    </svg>
  ');
}

function buildUrl($base, $params) {
  $clean = [];
  foreach ($params as $k => $v) {
    if ($v === null) continue;
    $v = (string)$v;
    if ($v === "") continue;
    $clean[$k] = $v;
  }
  $qs = http_build_query($clean);
  return $base . ($qs ? ("?" . $qs) : "");
}

$q        = trim((string)($_GET["q"] ?? ""));
$category = trim((string)($_GET["category"] ?? ""));
$sort     = trim((string)($_GET["sort"] ?? "newest"));
$page     = (int)($_GET["page"] ?? 1);
if ($page < 1) $page = 1;

$perPage = 12;

if ($category !== "" && !in_array($category, $categories, true)) {
  $category = "";
}

$sortMap = [
  "newest"     => "s.created_date DESC",
  "oldest"     => "s.created_date ASC",
  "price_asc"  => "s.price ASC",
  "price_desc" => "s.price DESC",
];
if (!isset($sortMap[$sort])) $sort = "newest";
$orderBy = $sortMap[$sort];

$where = ["s.status = 'Active'"];
$params = [];

if ($q !== "") {
  $where[] = "(LOWER(s.title) LIKE :q OR LOWER(s.description) LIKE :q)";
  $params[":q"] = "%" . mb_strtolower($q, "UTF-8") . "%";
}
if ($category !== "") {
  $where[] = "s.category = :cat";
  $params[":cat"] = $category;
}

$whereSql = "WHERE " . implode(" AND ", $where);

$countSql = "SELECT COUNT(*) FROM services s $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalResults = (int)$countStmt->fetchColumn();

$totalPages = (int)ceil($totalResults / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;

$featuredSql = "
  SELECT
    s.service_id, s.title, s.category, s.price, s.featured_status, s.image_1, s.created_date,
    u.first_name, u.last_name, u.profile_photo
  FROM services s
  JOIN users u ON u.user_id = s.freelancer_id
  $whereSql
    AND s.featured_status = 'Yes'
  ORDER BY s.created_date DESC
  LIMIT 4
";
$featuredStmt = $pdo->prepare($featuredSql);
$featuredStmt->execute($params);
$featuredRows = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);

$listSql = "
  SELECT
    s.service_id, s.title, s.category, s.price, s.featured_status, s.image_1, s.created_date,
    u.first_name, u.last_name, u.profile_photo
  FROM services s
  JOIN users u ON u.user_id = s.freelancer_id
  $whereSql
  ORDER BY $orderBy
  LIMIT :limit OFFSET :offset
";
$listStmt = $pdo->prepare($listSql);
foreach ($params as $k => $v) $listStmt->bindValue($k, $v, PDO::PARAM_STR);
$listStmt->bindValue(":limit",  $perPage, PDO::PARAM_INT);
$listStmt->bindValue(":offset", $offset,  PDO::PARAM_INT);
$listStmt->execute();
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$currentParams = [
  "q" => $q,
  "category" => $category,
  "sort" => $sort,
];

$clearUrl = "browse-services.php";

include __DIR__ . "/includes/header.php";
?>

<div class="wrapper">
  <div class="container">
    <?php include __DIR__ . "/includes/nav.php"; ?>

    <main class="main-content">
      <h1 class="heading-primary">Browse Services</h1>

      <div class="browse-filter-bar">
        <form class="form" method="get" action="browse-services.php">
          <div class="browse-filter-row">

            <div class="form-group">
              <label class="form-label" for="q">Search</label>
              <input class="form-input" type="text" id="q" name="q"
                     value="<?= h($q) ?>"
                     placeholder="Search by title or description">
            </div>

            <div class="form-group">
              <label class="form-label" for="category">Category</label>
              <select class="form-select" id="category" name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= h($cat) ?>" <?= ($category === $cat) ? "selected" : "" ?>>
                    <?= h($cat) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="sort">Sort</label>
              <select class="form-select" id="sort" name="sort">
                <option value="newest" <?= $sort === "newest" ? "selected" : "" ?>>Newest</option>
                <option value="oldest" <?= $sort === "oldest" ? "selected" : "" ?>>Oldest</option>
                <option value="price_asc" <?= $sort === "price_asc" ? "selected" : "" ?>>Price: Low to High</option>
                <option value="price_desc" <?= $sort === "price_desc" ? "selected" : "" ?>>Price: High to Low</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label form-label-hidden">Apply</label>
              <button class="btn btn-primary" type="submit">Apply</button>
            </div>

          </div>

          <div class="form-actions browse-filter-actions">
            <a class="btn btn-secondary" href="<?= h($clearUrl) ?>">Show All Services</a>
          </div>
        </form>
      </div>

      <div class="browse-results-summary">
        <?php if ($q !== "" || $category !== ""): ?>
          <div class="message-info">
            <?php
              $parts = [];
              if ($q !== "") $parts[] = "Search results for '" . h($q) . "'";
              if ($category !== "") $parts[] = "Category: " . h($category);
              echo implode(" | ", $parts);
            ?>
            — <?= (int)$totalResults ?> result(s)
          </div>
        <?php else: ?>
          <p class="text-muted">
            Showing all active services — <?= (int)$totalResults ?> result(s)
          </p>
        <?php endif; ?>
      </div>

      <?php if (!empty($featuredRows)): ?>
        <h2 class="heading-secondary">Featured Services</h2>

        <div class="services-grid services-grid-featured">
          <?php foreach ($featuredRows as $r): ?>
            <?php
              $imgSrc = img($baseUrl, $r["image_1"] ?? "", $servicePlaceholderRel, "uploads/services");
              $avatarSrc = img($baseUrl, $r["profile_photo"] ?? "", $userPlaceholderRel, "uploads/profile-photo", true);

              $isFeatured = (trim(strtolower((string)$r["featured_status"])) === "yes" || trim((string)$r["featured_status"]) === "1");

              $name = trim((string)($r["first_name"] ?? "") . " " . (string)($r["last_name"] ?? ""));
              if ($name === "") $name = "Freelancer";

              $detailUrl = "service-detail.php?id=" . urlencode((string)$r["service_id"]);

              $cardClasses = "card service-card";
              if ($isFeatured) $cardClasses .= " card-featured service-card-featured";
            ?>

            <a class="<?= h($cardClasses) ?>" href="<?= h($detailUrl) ?>">
              <div class="card-header service-card-image-wrap">
                <img class="service-card-image" src="<?= h($imgSrc) ?>" alt="Service image">
                <?php if ($isFeatured): ?>
                  <span class="badge badge-featured service-card-badge">Featured</span>
                <?php endif; ?>
              </div>

              <div class="card-body">
                <div class="service-card-title"><?= h($r["title"]) ?></div>

                <div class="service-card-freelancer">
                  <img class="service-card-avatar" src="<?= h($avatarSrc) ?>" alt="Freelancer photo">
                  <span><?= h($name) ?></span>
                </div>

                <div class="service-card-category"><?= h($r["category"]) ?></div>
              </div>

              <div class="card-footer">
                <div class="service-card-price">Starting at $<?= h(number_format((float)$r["price"], 2)) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($rows)): ?>
        <div class="services-grid">
          <?php foreach ($rows as $r): ?>
            <?php
              $imgSrc = img($baseUrl, $r["image_1"] ?? "", $servicePlaceholderRel, "uploads/services");
              $avatarSrc = img($baseUrl, $r["profile_photo"] ?? "", $userPlaceholderRel, "uploads/profile-photo", true);

              $isFeatured = (trim(strtolower((string)$r["featured_status"])) === "yes" || trim((string)$r["featured_status"]) === "1");

              $name = trim((string)($r["first_name"] ?? "") . " " . (string)($r["last_name"] ?? ""));
              if ($name === "") $name = "Freelancer";

              $detailUrl = "service-detail.php?id=" . urlencode((string)$r["service_id"]);

              $cardClasses = "card service-card";
              if ($isFeatured) $cardClasses .= " card-featured service-card-featured";
            ?>

            <a class="<?= h($cardClasses) ?>" href="<?= h($detailUrl) ?>">
              <div class="card-header service-card-image-wrap">
                <img class="service-card-image" src="<?= h($imgSrc) ?>" alt="Service image">
                <?php if ($isFeatured): ?>
                  <span class="badge badge-featured service-card-badge">Featured</span>
                <?php endif; ?>
              </div>

              <div class="card-body">
                <div class="service-card-title"><?= h($r["title"]) ?></div>

                <div class="service-card-freelancer">
                  <img class="service-card-avatar" src="<?= h($avatarSrc) ?>" alt="Freelancer photo">
                  <span><?= h($name) ?></span>
                </div>

                <div class="service-card-category"><?= h($r["category"]) ?></div>
              </div>

              <div class="card-footer">
                <div class="service-card-price">Starting at $<?= h(number_format((float)$r["price"], 2)) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php
              $prevPage = $page - 1;
              $nextPage = $page + 1;

              $prevUrl = buildUrl("browse-services.php", array_merge($currentParams, ["page" => $prevPage]));
              $nextUrl = buildUrl("browse-services.php", array_merge($currentParams, ["page" => $nextPage]));
            ?>

            <?php if ($page <= 1): ?>
              <span class="pagination-btn pagination-disabled">Previous</span>
            <?php else: ?>
              <a class="pagination-btn" href="<?= h($prevUrl) ?>">Previous</a>
            <?php endif; ?>

            <?php
              $start = max(1, $page - 2);
              $end   = min($totalPages, $page + 2);

              if ($start > 1) {
                $u = buildUrl("browse-services.php", array_merge($currentParams, ["page" => 1]));
                echo '<a class="pagination-btn" href="'.h($u).'">1</a>';
                if ($start > 2) echo '<span class="pagination-btn pagination-disabled">...</span>';
              }

              for ($p = $start; $p <= $end; $p++) {
                $u = buildUrl("browse-services.php", array_merge($currentParams, ["page" => $p]));
                if ($p === $page) {
                  echo '<span class="pagination-btn pagination-active">'.(int)$p.'</span>';
                } else {
                  echo '<a class="pagination-btn" href="'.h($u).'">'.(int)$p.'</a>';
                }
              }

              if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo '<span class="pagination-btn pagination-disabled">...</span>';
                $u = buildUrl("browse-services.php", array_merge($currentParams, ["page" => $totalPages]));
                echo '<a class="pagination-btn" href="'.h($u).'">'.(int)$totalPages.'</a>';
              }
            ?>

            <?php if ($page >= $totalPages): ?>
              <span class="pagination-btn pagination-disabled">Next</span>
            <?php else: ?>
              <a class="pagination-btn" href="<?= h($nextUrl) ?>">Next</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="message-info">
          No services found.
          <a class="action-link" href="<?= h($clearUrl) ?>">Show All Services</a>
        </div>
      <?php endif; ?>

    </main>

    <aside class="sidebar">
      <h3 class="heading-secondary">Sidebar</h3>
      <p class="text-muted">Use the search and filters to find services.</p>
    </aside>
  </div>

  <?php include "includes/footer.php"; ?>
</div>