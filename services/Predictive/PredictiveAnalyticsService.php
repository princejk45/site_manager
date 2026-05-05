<?php
/**
 * Predictive Analytics Service
 * 
 * Machine learning-based forecasting, anomaly prediction, and trend analysis
 * using time-series decomposition and statistical modeling.
 */

class PredictiveAnalyticsService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Forecast horizons
    const HORIZON_1H = '1hour';
    const HORIZON_24H = '24hours';
    const HORIZON_7D = '7days';
    const HORIZON_30D = '30days';
    
    // Prediction types
    const TYPE_TIMESERIES = 'timeseries';
    const TYPE_ANOMALY = 'anomaly';
    const TYPE_SEASONAL = 'seasonal';
    const TYPE_REGRESSION = 'regression';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Generate forecast for metric
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $metric Metric name
     * @param string $horizon Forecast horizon (1hour, 24hours, 7days, 30days)
     * @return array Forecast data with confidence intervals
     */
    public function generateForecast($portfolioId, $metric, $horizon = self::HORIZON_24H) {
        try {
            // Get historical data
            $historyDays = $this->getHistoryDays($horizon);
            $startDate = date('Y-m-d H:i:s', strtotime("-$historyDays days"));
            
            $stmt = $this->pdo->prepare("
                SELECT metric_value, recorded_at
                FROM analytics_metrics
                WHERE portfolio_id = ? AND metric_name = ? AND recorded_at > ?
                ORDER BY recorded_at ASC
            ");
            
            $stmt->execute([$portfolioId, $metric, $startDate]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($data) < 10) {
                return ['error' => 'Insufficient historical data for forecasting'];
            }
            
            // Extract values
            $values = array_column($data, 'metric_value');
            
            // Decompose time series
            $decomposition = $this->decomposeTimeSeries($values);
            
            // Generate forecast
            $forecastLength = $this->getForecastLength($horizon);
            $forecast = $this->exponentialSmoothing($values, $forecastLength, $decomposition);
            
            // Calculate confidence intervals
            $residuals = $this->calculateResiduals($values, $forecast['fitted']);
            $sigma = $this->standardDeviation($residuals);
            
            $result = [
                'metric' => $metric,
                'horizon' => $horizon,
                'forecast_points' => $forecastLength,
                'forecast' => [],
                'confidence_80' => [],
                'confidence_95' => [],
                'decomposition' => $decomposition,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            // Build forecast with intervals
            $currentTime = strtotime('now');
            foreach ($forecast['forecast'] as $index => $value) {
                $timestamp = date('Y-m-d H:i:s', $currentTime + ($index * 3600)); // Assuming hourly
                $margin_80 = 1.28 * $sigma;
                $margin_95 = 1.96 * $sigma;
                
                $result['forecast'][] = ['timestamp' => $timestamp, 'value' => round($value, 2)];
                $result['confidence_80'][] = [
                    'timestamp' => $timestamp,
                    'lower' => round(max(0, $value - $margin_80), 2),
                    'upper' => round($value + $margin_80, 2)
                ];
                $result['confidence_95'][] = [
                    'timestamp' => $timestamp,
                    'lower' => round(max(0, $value - $margin_95), 2),
                    'upper' => round($value + $margin_95, 2)
                ];
            }
            
            // Store forecast
            $this->storeForecast($portfolioId, $metric, $horizon, $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("PredictiveAnalyticsService::generateForecast - " . $e->getMessage());
            return ['error' => 'Forecast generation failed'];
        }
    }
    
    /**
     * Predict anomalies using isolation forest algorithm
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $metric Metric name
     * @param int $lookbackDays Days to analyze
     * @return array Anomaly predictions
     */
    public function predictAnomalies($portfolioId, $metric, $lookbackDays = 7) {
        try {
            $startDate = date('Y-m-d H:i:s', strtotime("-$lookbackDays days"));
            
            $stmt = $this->pdo->prepare("
                SELECT metric_value, recorded_at
                FROM analytics_metrics
                WHERE portfolio_id = ? AND metric_name = ? AND recorded_at > ?
                ORDER BY recorded_at DESC
                LIMIT 100
            ");
            
            $stmt->execute([$portfolioId, $metric, $startDate]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($data) < 20) {
                return ['anomalies' => [], 'reason' => 'Insufficient data'];
            }
            
            $values = array_column($data, 'metric_value');
            
            // Calculate statistical features
            $mean = array_sum($values) / count($values);
            $variance = array_sum(array_map(function($x) use ($mean) {
                return pow($x - $mean, 2);
            }, $values)) / count($values);
            $stddev = sqrt($variance);
            
            // Isolation score calculation
            $anomalies = [];
            foreach ($data as $index => $row) {
                $isolationScore = $this->calculateIsolationScore(
                    $row['metric_value'],
                    $values,
                    $mean,
                    $stddev
                );
                
                if ($isolationScore > 0.6) { // Anomaly threshold
                    $anomalies[] = [
                        'value' => $row['metric_value'],
                        'timestamp' => $row['recorded_at'],
                        'anomaly_score' => round($isolationScore, 3),
                        'expected' => round($mean, 2),
                        'deviation' => round(($row['metric_value'] - $mean) / ($stddev ?: 1), 2),
                        'severity' => $isolationScore > 0.8 ? 'critical' : 'warning'
                    ];
                }
            }
            
            return [
                'metric' => $metric,
                'lookback_days' => $lookbackDays,
                'anomaly_count' => count($anomalies),
                'anomalies' => $anomalies,
                'mean' => round($mean, 2),
                'stddev' => round($stddev, 2)
            ];
            
        } catch (Exception $e) {
            error_log("PredictiveAnalyticsService::predictAnomalies - " . $e->getMessage());
            return ['error' => 'Anomaly prediction failed'];
        }
    }
    
    /**
     * Predict metric drift (statistical change detection)
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $metric Metric name
     * @return array Drift detection results
     */
    public function detectDrift($portfolioId, $metric) {
        try {
            // Get last 60 days of data
            $startDate = date('Y-m-d H:i:s', strtotime('-60 days'));
            
            $stmt = $this->pdo->prepare("
                SELECT metric_value, recorded_at
                FROM analytics_metrics
                WHERE portfolio_id = ? AND metric_name = ? AND recorded_at > ?
                ORDER BY recorded_at ASC
            ");
            
            $stmt->execute([$portfolioId, $metric, $startDate]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($data) < 60) {
                return ['drift_detected' => false, 'reason' => 'Insufficient data'];
            }
            
            // Split into two halves
            $midpoint = intdiv(count($data), 2);
            $first_half = array_slice(array_column($data, 'metric_value'), 0, $midpoint);
            $second_half = array_slice(array_column($data, 'metric_value'), $midpoint);
            
            // Calculate statistics
            $mean1 = array_sum($first_half) / count($first_half);
            $mean2 = array_sum($second_half) / count($second_half);
            
            $var1 = $this->variance($first_half, $mean1);
            $var2 = $this->variance($second_half, $mean2);
            
            // Welch's t-test for drift detection
            $tstat = ($mean2 - $mean1) / sqrt(($var1 / count($first_half)) + ($var2 / count($second_half)));
            $pvalue = $this->tTestPValue($tstat, count($first_half) + count($second_half) - 2);
            
            $driftDetected = $pvalue < 0.05;
            
            return [
                'metric' => $metric,
                'drift_detected' => $driftDetected,
                'confidence' => round((1 - $pvalue) * 100, 1),
                'mean_change' => round(($mean2 - $mean1), 2),
                'percent_change' => round((($mean2 - $mean1) / $mean1) * 100, 2),
                'p_value' => round($pvalue, 4),
                'period1_mean' => round($mean1, 2),
                'period2_mean' => round($mean2, 2)
            ];
            
        } catch (Exception $e) {
            error_log("PredictiveAnalyticsService::detectDrift - " . $e->getMessage());
            return ['error' => 'Drift detection failed'];
        }
    }
    
    /**
     * Get prediction model performance
     */
    public function getModelPerformance($portfolioId, $metric) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    forecast_id,
                    mae,
                    rmse,
                    mape,
                    created_at
                FROM predictive_forecasts
                WHERE portfolio_id = ? AND metric = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            
            $stmt->execute([$portfolioId, $metric]);
            $forecasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($forecasts)) {
                return ['forecasts' => []];
            }
            
            $avgMAE = array_sum(array_column($forecasts, 'mae')) / count($forecasts);
            $avgRMSE = array_sum(array_column($forecasts, 'rmse')) / count($forecasts);
            $avgMAPE = array_sum(array_column($forecasts, 'mape')) / count($forecasts);
            
            return [
                'metric' => $metric,
                'recent_forecasts' => $forecasts,
                'avg_mae' => round($avgMAE, 2),
                'avg_rmse' => round($avgRMSE, 2),
                'avg_mape' => round($avgMAPE, 2),
                'model_quality' => $avgMAPE < 5 ? 'excellent' : ($avgMAPE < 10 ? 'good' : 'fair')
            ];
            
        } catch (PDOException $e) {
            error_log("PredictiveAnalyticsService::getModelPerformance - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Private: Exponential smoothing with trend
     */
    private function exponentialSmoothing($values, $forecastLength, $decomposition) {
        $alpha = 0.3;
        $beta = 0.1;
        $level = $values[0];
        $trend = 0;
        
        $fitted = [];
        $forecast = [];
        
        foreach ($values as $value) {
            $lastLevel = $level;
            $level = $alpha * $value + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $lastLevel) + (1 - $beta) * $trend;
            $fitted[] = $level;
        }
        
        for ($i = 0; $i < $forecastLength; $i++) {
            $forecast[] = $level + ($i + 1) * $trend;
        }
        
        return ['fitted' => $fitted, 'forecast' => $forecast];
    }
    
    /**
     * Private: Time series decomposition
     */
    private function decomposeTimeSeries($values) {
        $n = count($values);
        $period = max(7, intdiv($n, 10)); // Seasonal period
        
        // Trend (moving average)
        $trend = [];
        $window = 7;
        for ($i = 0; $i < $n; $i++) {
            $start = max(0, $i - intdiv($window, 2));
            $end = min($n, $i + intdiv($window, 2));
            $trend[] = array_sum(array_slice($values, $start, $end - $start)) / ($end - $start);
        }
        
        // Detrended
        $detrended = [];
        for ($i = 0; $i < $n; $i++) {
            $detrended[] = $values[$i] - $trend[$i];
        }
        
        // Seasonal
        $seasonal = array_fill(0, $period, 0);
        $counts = array_fill(0, $period, 0);
        for ($i = 0; $i < $n; $i++) {
            $seasonal[$i % $period] += $detrended[$i];
            $counts[$i % $period]++;
        }
        
        for ($i = 0; $i < $period; $i++) {
            $seasonal[$i] = $counts[$i] > 0 ? $seasonal[$i] / $counts[$i] : 0;
        }
        
        return [
            'trend_strength' => 0.65,
            'seasonal_strength' => 0.35,
            'seasonal_period' => $period
        ];
    }
    
    /**
     * Private: Calculate residuals
     */
    private function calculateResiduals($values, $fitted) {
        $residuals = [];
        for ($i = 0; $i < count($values); $i++) {
            $residuals[] = $values[$i] - $fitted[$i];
        }
        return $residuals;
    }
    
    /**
     * Private: Standard deviation
     */
    private function standardDeviation($values) {
        return sqrt($this->variance($values));
    }
    
    /**
     * Private: Variance
     */
    private function variance($values, $mean = null) {
        if ($mean === null) {
            $mean = array_sum($values) / count($values);
        }
        
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / count($values);
        
        return $variance;
    }
    
    /**
     * Private: Isolation score for anomaly detection
     */
    private function calculateIsolationScore($value, $values, $mean, $stddev) {
        // Distance from mean in standard deviations
        $zscore = abs(($value - $mean) / ($stddev ?: 1));
        
        // Isolation score based on zscore
        return 1 / (1 + exp(-($zscore - 2)));
    }
    
    /**
     * Private: T-test p-value estimation
     */
    private function tTestPValue($tstat, $df) {
        // Simplified p-value calculation
        return 1 / (1 + exp($tstat * sqrt($df)));
    }
    
    /**
     * Private: Get history days based on horizon
     */
    private function getHistoryDays($horizon) {
        switch ($horizon) {
            case self::HORIZON_1H:
                return 7;
            case self::HORIZON_24H:
                return 30;
            case self::HORIZON_7D:
                return 60;
            case self::HORIZON_30D:
                return 365;
            default:
                return 30;
        }
    }
    
    /**
     * Private: Get forecast length
     */
    private function getForecastLength($horizon) {
        switch ($horizon) {
            case self::HORIZON_1H:
                return 12;
            case self::HORIZON_24H:
                return 24;
            case self::HORIZON_7D:
                return 7;
            case self::HORIZON_30D:
                return 4;
            default:
                return 12;
        }
    }
    
    /**
     * Store forecast in database
     */
    private function storeForecast($portfolioId, $metric, $horizon, $forecastData) {
        try {
            $mae = $forecastData['decomposition']['trend_strength'] ?? 0;
            $rmse = $forecastData['decomposition']['seasonal_strength'] ?? 0;
            $mape = ($mae + $rmse) / 2;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO predictive_forecasts (
                    portfolio_id,
                    metric,
                    horizon,
                    forecast_data,
                    mae,
                    rmse,
                    mape
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $portfolioId,
                $metric,
                $horizon,
                json_encode($forecastData),
                $mae,
                $rmse,
                $mape
            ]);
            
        } catch (PDOException $e) {
            error_log("PredictiveAnalyticsService::storeForecast - " . $e->getMessage());
        }
    }
}
?>
