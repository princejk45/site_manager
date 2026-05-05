<?php
/**
 * PerformanceHardeningService
 * CDN integration, compression, and performance optimization
 */

namespace Services\Performance;

use PDO;
use Exception;

class PerformanceHardeningService
{
    private $db;
    private $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Configure CDN
     */
    public function configureCDN($portfolio_id, $cdn_provider, $api_key, $cdn_config = [])
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO cdn_configuration (portfolio_id, provider, api_key, configuration, is_enabled, created_at)
                VALUES (:portfolio_id, :provider, :api_key, :config, 1, NOW())
                ON DUPLICATE KEY UPDATE
                api_key = :api_key,
                configuration = :config,
                is_enabled = 1
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':provider' => $cdn_provider,
                ':api_key' => hash('sha256', $api_key),
                ':config' => json_encode($cdn_config)
            ]);

            return ['status' => 'success', 'message' => "CDN {$cdn_provider} configured"];
        } catch (\PDOException $e) {
            throw new Exception("Failed to configure CDN: " . $e->getMessage());
        }
    }

    /**
     * Enable content compression
     */
    public function enableCompression($portfolio_id, $compression_types = ['gzip', 'brotli'])
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO compression_settings (portfolio_id, compression_types, is_enabled, created_at)
                VALUES (:portfolio_id, :types, 1, NOW())
                ON DUPLICATE KEY UPDATE
                compression_types = :types,
                is_enabled = 1
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':types' => json_encode($compression_types)
            ]);

            // Add compression headers
            $this->setCompressionHeaders($compression_types);

            return ['status' => 'success', 'message' => 'Compression enabled'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to enable compression: " . $e->getMessage());
        }
    }

    /**
     * Enable browser caching
     */
    public function enableBrowserCaching($portfolio_id, $cache_rules = [])
    {
        try {
            // Default cache rules
            $default_rules = [
                'static_assets' => 2592000, // 30 days
                'images' => 2592000,
                'api_responses' => 300, // 5 minutes
                'html' => 3600 // 1 hour
            ];

            $rules = array_merge($default_rules, $cache_rules);

            $stmt = $this->db->prepare("
                INSERT INTO browser_cache_rules (portfolio_id, cache_rules, is_enabled, created_at)
                VALUES (:portfolio_id, :rules, 1, NOW())
                ON DUPLICATE KEY UPDATE
                cache_rules = :rules
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':rules' => json_encode($rules)
            ]);

            // Set cache headers
            $this->setCacheHeaders($rules);

            return ['status' => 'success', 'message' => 'Browser caching enabled'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to enable browser caching: " . $e->getMessage());
        }
    }

    /**
     * Optimize images
     */
    public function optimizeImages($portfolio_id, $image_paths = [])
    {
        try {
            $optimization_count = 0;

            foreach ($image_paths as $image_path) {
                if (!file_exists($image_path)) {
                    continue;
                }

                // Get original size
                $original_size = filesize($image_path);

                // Optimize using imagemagick
                $cmd = "convert '{$image_path}' -strip -interlace Plane -quality 85% '{$image_path}' 2>&1";
                shell_exec($cmd);

                // Get new size
                $optimized_size = filesize($image_path);
                $savings_percent = (($original_size - $optimized_size) / $original_size) * 100;

                // Log optimization
                $stmt = $this->db->prepare("
                    INSERT INTO image_optimization_log (portfolio_id, filepath, original_size, optimized_size, savings_percent, optimized_at)
                    VALUES (:portfolio_id, :path, :original, :optimized, :savings, NOW())
                ");

                $stmt->execute([
                    ':portfolio_id' => $portfolio_id,
                    ':path' => $image_path,
                    ':original' => $original_size,
                    ':optimized' => $optimized_size,
                    ':savings' => $savings_percent
                ]);

                $optimization_count++;
            }

            return [
                'status' => 'success',
                'optimized_count' => $optimization_count,
                'message' => "Optimized {$optimization_count} images"
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to optimize images: " . $e->getMessage());
        }
    }

    /**
     * Enable HTTP/2 push
     */
    public function enableHTTP2Push($portfolio_id, $push_resources = [])
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO http2_push_config (portfolio_id, push_resources, is_enabled, created_at)
                VALUES (:portfolio_id, :resources, 1, NOW())
                ON DUPLICATE KEY UPDATE
                push_resources = :resources
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':resources' => json_encode($push_resources)
            ]);

            return ['status' => 'success', 'message' => 'HTTP/2 push enabled'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to enable HTTP/2 push: " . $e->getMessage());
        }
    }

    /**
     * Minify assets
     */
    public function minifyAssets($portfolio_id, $asset_type = 'all')
    {
        try {
            $minified_count = 0;

            if ($asset_type === 'css' || $asset_type === 'all') {
                $minified_count += $this->minifyCSS($portfolio_id);
            }

            if ($asset_type === 'js' || $asset_type === 'all') {
                $minified_count += $this->minifyJavaScript($portfolio_id);
            }

            return [
                'status' => 'success',
                'minified_count' => $minified_count,
                'message' => "Minified {$minified_count} assets"
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to minify assets: " . $e->getMessage());
        }
    }

    /**
     * Configure rate limiting
     */
    public function configureRateLimiting($portfolio_id, $limits = [])
    {
        try {
            $default_limits = [
                'api_requests_per_minute' => 60,
                'login_attempts_per_hour' => 10,
                'file_upload_per_day' => 100
            ];

            $final_limits = array_merge($default_limits, $limits);

            $stmt = $this->db->prepare("
                INSERT INTO rate_limit_config (portfolio_id, limits, is_enabled, created_at)
                VALUES (:portfolio_id, :limits, 1, NOW())
                ON DUPLICATE KEY UPDATE
                limits = :limits
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':limits' => json_encode($final_limits)
            ]);

            return ['status' => 'success', 'message' => 'Rate limiting configured'];
        } catch (\PDOException $e) {
            throw new Exception("Failed to configure rate limiting: " . $e->getMessage());
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics($portfolio_id, $time_period = 24)
    {
        try {
            $start_time = date('Y-m-d H:i:s', strtotime("-{$time_period} hours"));

            $stmt = $this->db->prepare("
                SELECT
                    AVG(response_time_ms) as avg_response_time,
                    MAX(response_time_ms) as max_response_time,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN response_time_ms > 1000 THEN 1 ELSE 0 END) as slow_requests,
                    SUM(bytes_transferred) as total_bytes_transferred,
                    COUNT(DISTINCT DATE(created_at)) as days_tracked
                FROM performance_metrics
                WHERE portfolio_id = :portfolio_id AND created_at > :start_time
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':start_time' => $start_time
            ]);

            $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate additional metrics
            $slow_request_percent = ($metrics['total_requests'] > 0) 
                ? ($metrics['slow_requests'] / $metrics['total_requests']) * 100 
                : 0;

            return [
                'status' => 'success',
                'metrics' => $metrics,
                'slow_request_percent' => round($slow_request_percent, 2),
                'period_hours' => $time_period
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get performance metrics: " . $e->getMessage());
        }
    }

    /**
     * Get optimization recommendations
     */
    public function getOptimizationRecommendations($portfolio_id)
    {
        try {
            $recommendations = [];

            // Check compression
            $stmt = $this->db->prepare("SELECT is_enabled FROM compression_settings WHERE portfolio_id = :portfolio_id");
            $stmt->execute([':portfolio_id' => $portfolio_id]);
            if (!$stmt->fetch()) {
                $recommendations[] = [
                    'type' => 'compression',
                    'priority' => 'high',
                    'description' => 'Enable gzip/brotli compression',
                    'potential_savings' => '60-70% bandwidth'
                ];
            }

            // Check browser caching
            $stmt = $this->db->prepare("SELECT is_enabled FROM browser_cache_rules WHERE portfolio_id = :portfolio_id");
            $stmt->execute([':portfolio_id' => $portfolio_id]);
            if (!$stmt->fetch()) {
                $recommendations[] = [
                    'type' => 'browser_cache',
                    'priority' => 'high',
                    'description' => 'Enable browser caching headers',
                    'potential_savings' => '40-50% repeat visits'
                ];
            }

            // Check CDN
            $stmt = $this->db->prepare("SELECT is_enabled FROM cdn_configuration WHERE portfolio_id = :portfolio_id");
            $stmt->execute([':portfolio_id' => $portfolio_id]);
            if (!$stmt->fetch()) {
                $recommendations[] = [
                    'type' => 'cdn',
                    'priority' => 'medium',
                    'description' => 'Configure CDN for global distribution',
                    'potential_savings' => '30-50% latency'
                ];
            }

            // Check HTTP/2
            $stmt = $this->db->prepare("SELECT is_enabled FROM http2_push_config WHERE portfolio_id = :portfolio_id");
            $stmt->execute([':portfolio_id' => $portfolio_id]);
            if (!$stmt->fetch()) {
                $recommendations[] = [
                    'type' => 'http2_push',
                    'priority' => 'medium',
                    'description' => 'Enable HTTP/2 server push',
                    'potential_savings' => '20-30% load time'
                ];
            }

            return [
                'status' => 'success',
                'recommendations' => $recommendations,
                'count' => count($recommendations)
            ];
        } catch (\PDOException $e) {
            throw new Exception("Failed to get recommendations: " . $e->getMessage());
        }
    }

    /**
     * Record performance metric
     */
    public function recordMetric($portfolio_id, $response_time_ms, $bytes_transferred)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO performance_metrics (portfolio_id, response_time_ms, bytes_transferred, created_at)
                VALUES (:portfolio_id, :response_time, :bytes, NOW())
            ");

            $stmt->execute([
                ':portfolio_id' => $portfolio_id,
                ':response_time' => $response_time_ms,
                ':bytes' => $bytes_transferred
            ]);
        } catch (\PDOException $e) {
            // Silent fail - don't break request for metrics
        }
    }

    /**
     * Set compression headers
     */
    private function setCompressionHeaders($compression_types)
    {
        if (in_array('gzip', $compression_types)) {
            header('Content-Encoding: gzip');
        }
    }

    /**
     * Set cache headers
     */
    private function setCacheHeaders($cache_rules)
    {
        $max_age = $cache_rules['api_responses'] ?? 300;
        header("Cache-Control: public, max-age={$max_age}");
        header("ETag: " . md5(time()));
    }

    /**
     * Minify CSS
     */
    private function minifyCSS($portfolio_id)
    {
        // Implementation would use CSS minification library
        return 0;
    }

    /**
     * Minify JavaScript
     */
    private function minifyJavaScript($portfolio_id)
    {
        // Implementation would use JS minification library
        return 0;
    }
}
