<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/config.php';

class CountryService {
    private PDO $pdo;
    private array $env;
    private string $countries_api = 'https://restcountries.com/v2/all?fields=name,capital,region,population,flag,currencies';
    private string $exchange_api = 'https://open.er-api.com/v6/latest/USD';

    public function __construct() {
        $this->pdo = get_pdo();
        global $ENV;
        $this->env = $ENV;
    }

    public function refresh(): void {
        error_log("DEBUG >>> step 1: starting external fetch");

        $countries = http_get_json($this->countries_api);
        if ($countries === null) service_unavailable('Countries API');

        error_log("DEBUG >>> step 2: countries fetched: " . count($countries));

        $ratesResp = http_get_json($this->exchange_api);
        if ($ratesResp === null || !isset($ratesResp['rates'])) service_unavailable('Exchange Rates API');

        $rates = $ratesResp['rates'];
        error_log("DEBUG >>> step 3: exchange rates fetched");

        $pdo = $this->pdo;
        try {
            $pdo->beginTransaction();
            error_log("DEBUG >>> step 4: transaction started");

            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            // prepare statements once
            $upsertStmt = $pdo->prepare("SELECT id FROM countries WHERE LOWER(name) = LOWER(:name) LIMIT 1");
            $updateStmt = $pdo->prepare(
                "UPDATE countries SET capital = :capital, region = :region, population = :population,
             currency_code = :currency_code, exchange_rate = :exchange_rate, estimated_gdp = :estimated_gdp,
             flag_url = :flag_url, last_refreshed_at = :last_refreshed_at WHERE id = :id"
            );
            $insertStmt = $pdo->prepare(
                "INSERT INTO countries (name, capital, region, population, currency_code, exchange_rate, estimated_gdp, flag_url, last_refreshed_at)
             VALUES (:name, :capital, :region, :population, :currency_code, :exchange_rate, :estimated_gdp, :flag_url, :last_refreshed_at)"
            );

            foreach ($countries as $c) {
                if (!isset($c['name'])) continue;

                $name = $c['name'];
                $population = isset($c['population']) ? (int)$c['population'] : 0;
                $capital = $c['capital'] ?? null;
                $region = $c['region'] ?? null;
                $flag = $c['flag'] ?? null;

                $currency_code = null;
                if (!empty($c['currencies']) && is_array($c['currencies'])) {
                    $first = reset($c['currencies']);
                    if (is_array($first) && !empty($first['code'])) {
                        $currency_code = $first['code'];
                    }
                }

                if ($currency_code === null) {
                    $exchange_rate = null;
                    $estimated_gdp = 0;
                } else {
                    $exchange_rate = $rates[$currency_code] ?? null;
                    $estimated_gdp = $exchange_rate === null ? null : ($population * random_int(1000, 2000) / $exchange_rate);
                }

                $upsertStmt->execute([':name' => $name]);
                $row = $upsertStmt->fetch();

                if ($row && isset($row['id'])) {
                    $updateStmt->execute([
                        ':capital' => $capital,
                        ':region' => $region,
                        ':population' => $population,
                        ':currency_code' => $currency_code,
                        ':exchange_rate' => $exchange_rate,
                        ':estimated_gdp' => $estimated_gdp,
                        ':flag_url' => $flag,
                        ':last_refreshed_at' => $now,
                        ':id' => $row['id']
                    ]);
                } else {
                    $insertStmt->execute([
                        ':name' => $name,
                        ':capital' => $capital,
                        ':region' => $region,
                        ':population' => $population,
                        ':currency_code' => $currency_code,
                        ':exchange_rate' => $exchange_rate,
                        ':estimated_gdp' => $estimated_gdp,
                        ':flag_url' => $flag,
                        ':last_refreshed_at' => $now
                    ]);
                }
            }

            // update meta
            $metaStmt = $pdo->prepare("INSERT INTO meta (k, v) VALUES ('last_refreshed_at', :v) ON DUPLICATE KEY UPDATE v = :v2");
            $metaStmt->execute([':v' => $now, ':v2' => $now]);

            $pdo->commit();
            error_log("DEBUG >>> step 6: commit done");

            $this->generate_summary_image($now);
            error_log("DEBUG >>> step 7: summary generated");

            json_response([
                'status' => 'ok',
                'last_refreshed_at' => (new DateTimeImmutable($now, new DateTimeZone('UTC')))->format(DateTime::ATOM)
            ]);
        } catch (Exception $ex) {
            error_log("DEBUG >>> EXCEPTION: " . $ex->getMessage());
            error_log($ex->getTraceAsString());
            if ($pdo->inTransaction()) $pdo->rollBack();
            internal_error('Internal server error');
        }
    }

    public function list(array $query): void {
        $sql = "SELECT id, name, capital, region, population, currency_code, exchange_rate, estimated_gdp, flag_url, last_refreshed_at FROM countries";
        $where = [];
        $params = [];
        if (!empty($query['region'])) $where[] = 'region = :region';
        if (!empty($query['currency'])) $where[] = 'currency_code = :currency';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        if (!empty($query['sort']) && $query['sort'] === 'gdp_desc') $sql .= ' ORDER BY estimated_gdp DESC';
        else $sql .= ' ORDER BY name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['last_refreshed_at'] = $this->to_iso_z($r['last_refreshed_at']);
            $r['population'] = (int)$r['population'];
            $r['exchange_rate'] = $r['exchange_rate'] !== null ? (float)$r['exchange_rate'] : null;
            $r['estimated_gdp'] = $r['estimated_gdp'] !== null ? (float)$r['estimated_gdp'] : null;
        }
        json_response(array_values($rows));
    }

    public function get_one(string $name): void {
        $stmt = $this->pdo->prepare("SELECT id, name, capital, region, population, currency_code, exchange_rate, estimated_gdp, flag_url, last_refreshed_at FROM countries WHERE LOWER(name) = LOWER(:name) LIMIT 1");
        $stmt->execute([':name' => $name]);
        $r = $stmt->fetch();
        if (!$r) not_found();

        $r['population'] = (int)$r['population'];
        $r['exchange_rate'] = $r['exchange_rate'] !== null ? (float)$r['exchange_rate'] : null;
        $r['estimated_gdp'] = $r['estimated_gdp'] !== null ? (float)$r['estimated_gdp'] : null;
        $r['last_refreshed_at'] = $this->to_iso_z($r['last_refreshed_at']);

        json_response($r);
    }

    public function delete_one(string $name): void {
        $stmt = $this->pdo->prepare("DELETE FROM countries WHERE LOWER(name) = LOWER(:name)");
        $stmt->execute([':name' => $name]);
        if ($stmt->rowCount() === 0) not_found();
        json_response(['status' => 'deleted']);
    }

    public function status(): void {
        $total = (int)$this->pdo->query("SELECT COUNT(*) FROM countries")->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT v FROM meta WHERE k = 'last_refreshed_at' LIMIT 1");
        $stmt->execute();
        $v = $stmt->fetchColumn();
        $iso = $v ? (new DateTimeImmutable($v, new DateTimeZone('UTC')))->format(DateTime::ATOM) : null;
        json_response(['total_countries' => $total, 'last_refreshed_at' => $iso]);
    }

    public function serve_image(): void {
        global $ENV;
        $path = rtrim($ENV['CACHE_DIR'], '/\\') . '/summary.png';
        if (!file_exists($path)) json_response(['error' => 'Summary image not found'], 404);

        // Detect mime type and serve accordingly. If a real PNG exists, send image/png.
        $mime = null;
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
        } elseif (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $mime = finfo_file($f, $path);
                finfo_close($f);
            }
        }

        if ($mime && strpos($mime, 'image/') === 0) {
            header('Content-Type: ' . $mime);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
        }
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function to_iso_z(?string $dt): ?string {
        if ($dt === null) return null;
        $d = new DateTimeImmutable($dt, new DateTimeZone('UTC'));
        return $d->format(DateTime::ATOM);
    }

    private function generate_summary_image(string $now): void {
        global $ENV;
        $cacheDir = rtrim($ENV['CACHE_DIR'], '/\\');
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
        $outPath = $cacheDir . '/summary.png';

        $total = (int)$this->pdo->query("SELECT COUNT(*) FROM countries")->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT name, estimated_gdp FROM countries WHERE estimated_gdp IS NOT NULL ORDER BY estimated_gdp DESC LIMIT 5");
        $stmt->execute();
        $top = $stmt->fetchAll();

        $lines = [];
        $lines[] = "Countries Summary";
        $lines[] = "Last refreshed: " . (new DateTimeImmutable($now, new DateTimeZone('UTC')))->format('Y-m-d H:i:s UTC');
        $lines[] = "Total countries: {$total}";
        $lines[] = "Top 5 countries by estimated GDP:";
        $rank = 1;
        foreach ($top as $row) {
            $gdp = number_format((float)$row['estimated_gdp'], 2);
            $lines[] = "{$rank}. {$row['name']} — {$gdp}";
            $rank++;
        }
        $lines[] = "";
        $lines[] = "Generated by Country Currency & Exchange API";

        if (function_exists('imagecreatetruecolor')) {
            $width = 800;
            $height = 200 + (count($lines) * 16);
            $img = imagecreatetruecolor($width, $height);
            $bg = imagecolorallocate($img, 255, 255, 255);
            $textColor = imagecolorallocate($img, 20, 20, 20);
            $titleColor = imagecolorallocate($img, 0, 70, 140);
            imagefilledrectangle($img, 0, 0, $width, $height, $bg);

            imagestring($img, 5, 10, 8, $lines[0], $titleColor);

            $y = 36;
            for ($i = 1; $i < count($lines); $i++) {
                imagestring($img, 3, 10, $y, $lines[$i], $textColor);
                $y += 16;
            }

            imagepng($img, $outPath);
            imagedestroy($img);
        } else {
            error_log("DEBUG >>> GD not available; writing text summary instead of PNG");
            file_put_contents($outPath, implode(PHP_EOL, $lines));
        }
    }
}
