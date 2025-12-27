/**
 * Performance Monitoring Utility
 * Implements T129: Performance monitoring for API response times and timer precision
 *
 * Features:
 * - Track API response times (percentiles)
 * - Monitor timer precision metrics
 * - Memory and CPU usage tracking
 * - Export metrics for monitoring systems
 */

export interface MetricSnapshot {
  timestamp: number;
  value: number;
  labels: Record<string, string>;
}

export interface MetricSummary {
  count: number;
  min: number;
  max: number;
  mean: number;
  p50: number;
  p95: number;
  p99: number;
}

class MetricsCollector {
  private apiResponseTimes: Map<string, MetricSnapshot[]> = new Map();
  private timerPrecisionMetrics: MetricSnapshot[] = [];
  private syncMetrics: Map<string, MetricSnapshot[]> = new Map();

  /**
   * Record API response time
   */
  recordApiResponse(endpoint: string, duration: number, statusCode: number): void {
    const key = `${endpoint}:${statusCode}`;
    if (!this.apiResponseTimes.has(key)) {
      this.apiResponseTimes.set(key, []);
    }

    this.apiResponseTimes.get(key)!.push({
      timestamp: Date.now(),
      value: duration,
      labels: {
        endpoint,
        status_code: statusCode.toString(),
      },
    });
  }

  /**
   * Record timer precision metric
   */
  recordTimerPrecision(driftMs: number, tournamentId: string): void {
    this.timerPrecisionMetrics.push({
      timestamp: Date.now(),
      value: Math.abs(driftMs),
      labels: {
        tournament_id: tournamentId,
      },
    });
  }

  /**
   * Record sync operation metric
   */
  recordSyncOperation(operation: string, duration: number, success: boolean): void {
    const key = `${operation}:${success ? 'success' : 'failure'}`;
    if (!this.syncMetrics.has(key)) {
      this.syncMetrics.set(key, []);
    }

    this.syncMetrics.get(key)!.push({
      timestamp: Date.now(),
      value: duration,
      labels: {
        operation,
        success: success.toString(),
      },
    });
  }

  /**
   * Calculate metric summary
   */
  private calculateSummary(metrics: MetricSnapshot[]): MetricSummary {
    if (metrics.length === 0) {
      return { count: 0, min: 0, max: 0, mean: 0, p50: 0, p95: 0, p99: 0 };
    }

    const values = metrics.map((m) => m.value).sort((a, b) => a - b);
    const count = values.length;
    const sum = values.reduce((a, b) => a + b, 0);

    return {
      count,
      min: values[0],
      max: values[count - 1],
      mean: sum / count,
      p50: values[Math.floor(count * 0.5)],
      p95: values[Math.floor(count * 0.95)],
      p99: values[Math.floor(count * 0.99)],
    };
  }

  /**
   * Get API response time summary
   */
  getApiResponseSummary(endpoint?: string): Map<string, MetricSummary> {
    const summaries = new Map<string, MetricSummary>();

    for (const [key, metrics] of this.apiResponseTimes) {
      if (endpoint && !key.startsWith(endpoint)) {
        continue;
      }

      summaries.set(key, this.calculateSummary(metrics));
    }

    return summaries;
  }

  /**
   * Get timer precision summary
   */
  getTimerPrecisionSummary(): MetricSummary {
    return this.calculateSummary(this.timerPrecisionMetrics);
  }

  /**
   * Get sync operation summary
   */
  getSyncSummary(): Map<string, MetricSummary> {
    const summaries = new Map<string, MetricSummary>();

    for (const [key, metrics] of this.syncMetrics) {
      summaries.set(key, this.calculateSummary(metrics));
    }

    return summaries;
  }

  /**
   * Export metrics as JSON for monitoring systems
   */
  exportMetrics(): {
    apiResponseTimes: Record<string, MetricSummary>;
    timerPrecision: MetricSummary;
    syncOperations: Record<string, MetricSummary>;
    timestamp: string;
  } {
    const apiSummaries: Record<string, MetricSummary> = {};
    for (const [key, summary] of this.getApiResponseSummary()) {
      apiSummaries[key] = summary;
    }

    const syncSummaries: Record<string, MetricSummary> = {};
    for (const [key, summary] of this.getSyncSummary()) {
      syncSummaries[key] = summary;
    }

    return {
      apiResponseTimes: apiSummaries,
      timerPrecision: this.getTimerPrecisionSummary(),
      syncOperations: syncSummaries,
      timestamp: new Date().toISOString(),
    };
  }

  /**
   * Clear old metrics (prevent memory leaks)
   */
  clearMetrics(olderThan: number = 3600000): void {
    const cutoff = Date.now() - olderThan;

    for (const [key, metrics] of this.apiResponseTimes) {
      const filtered = metrics.filter((m) => m.timestamp > cutoff);
      this.apiResponseTimes.set(key, filtered);
    }

    this.timerPrecisionMetrics = this.timerPrecisionMetrics.filter(
      (m) => m.timestamp > cutoff
    );

    for (const [key, metrics] of this.syncMetrics) {
      const filtered = metrics.filter((m) => m.timestamp > cutoff);
      this.syncMetrics.set(key, filtered);
    }
  }
}

// Singleton instance
export const metrics = new MetricsCollector();

// Middleware for Fastify
export function metricsMiddleware() {
  return async (request: any, reply: any) => {
    const start = Date.now();

    reply.sent.then(() => {
      const duration = Date.now() - start;
      metrics.recordApiResponse(request.raw.url || request.routeOptions.url, duration, reply.statusCode);
    });
  };
}
