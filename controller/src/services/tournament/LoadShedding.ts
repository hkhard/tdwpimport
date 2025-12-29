/**
 * Load Shedding Service
 * Gracefully handles high load by shedding low-priority requests
 *
 * Constitution Requirements:
 * - US5-A1: 100+ concurrent tournaments
 * - Handle 100 sync requests/sec
 */

export interface LoadSheddingConfig {
  /** Max requests per second */
  maxRequestsPerSecond?: number;
  /** Max concurrent requests */
  maxConcurrentRequests?: number;
  /** Request timeout (ms) */
  requestTimeout?: number;
  /** Queue size */
  queueSize?: number;
}

export interface RequestPriority {
  /** Priority level (0 = highest, 10 = lowest) */
  level: number;
  /** Request type */
  type: 'timer_update' | 'sync' | 'crud' | 'query';
}

export interface LoadSheddingStats {
  /** Total requests received */
  totalRequests: number;
  /** Requests accepted */
  acceptedRequests: number;
  /** Requests rejected (shed) */
  rejectedRequests: number;
  /** Current concurrent requests */
  concurrentRequests: number;
  /** Current queue size */
  queueSize: number;
  /** Requests per second */
  requestsPerSecond: number;
  /** Reject ratio */
  rejectRatio: number;
}

/**
 * Load Shedding Service
 *
 * Protects system from overload:
 * - Rate limiting by request type
 * - Priority-based queuing
 * - Graceful rejection of low-priority requests
 * - Circuit breaker for failing endpoints
 */
export class LoadSheddingService {
  private config: Required<LoadSheddingConfig>;
  private requestCounts: Map<string, number[]> = new Map();
  private concurrentRequests: number = 0;
  private totalRequests: number = 0;
  private acceptedRequests: number = 0;
  private rejectedRequests: number = 0;
  private startTime: number = Date.now();

  // Circuit breaker state
  private circuitBreakers: Map<string, { isOpen: boolean; failures: number; lastFailure: number }> = new Map();

  constructor(config: LoadSheddingConfig = {}) {
    this.config = {
      maxRequestsPerSecond: config.maxRequestsPerSecond || 100,
      maxConcurrentRequests: config.maxConcurrentRequests || 50,
      requestTimeout: config.requestTimeout || 5000,
      queueSize: config.queueSize || 100,
    };
  }

  /**
   * Check if request should be accepted
   */
  canAcceptRequest(priority: RequestPriority): boolean {
    this.totalRequests++;

    // Check concurrent request limit
    if (this.concurrentRequests >= this.config.maxConcurrentRequests) {
      // Only accept high-priority requests
      if (priority.level > 5) {
        this.rejectRequest();
        return false;
      }
    }

    // Check rate limit for request type
    const typeKey = `type_${priority.type}`;
    const typeCounts = this.requestCounts.get(typeKey) || [];

    // Clean old requests outside the 1-second window
    const now = Date.now();
    const recentCounts = typeCounts.filter((timestamp) => now - timestamp < 1000);

    // Check type-specific limits
    let typeLimit = this.config.maxRequestsPerSecond;
    switch (priority.type) {
      case 'timer_update':
        typeLimit = this.config.maxRequestsPerSecond; // Highest priority
        break;
      case 'sync':
        typeLimit = Math.floor(this.config.maxRequestsPerSecond * 0.5); // Lower limit for sync
        break;
      case 'crud':
        typeLimit = Math.floor(this.config.maxRequestsPerSecond * 0.3);
        break;
      case 'query':
        typeLimit = Math.floor(this.config.maxRequestsPerSecond * 0.2); // Lowest limit for queries
        break;
    }

    if (recentCounts.length >= typeLimit) {
      this.rejectRequest();
      return false;
    }

    // Check circuit breaker
    const circuitBreakerKey = `${priority.type}_breaker`;
    const circuitBreaker = this.circuitBreakers.get(circuitBreakerKey);
    if (circuitBreaker?.isOpen) {
      // Check if circuit breaker should be reset (after 30 seconds)
      if (now - circuitBreaker.lastFailure > 30000) {
        circuitBreaker.isOpen = false;
        circuitBreaker.failures = 0;
      } else {
        this.rejectRequest();
        return false;
      }
    }

    // Accept request
    this.acceptRequest();
    recentCounts.push(now);
    this.requestCounts.set(typeKey, recentCounts);
    return true;
  }

  /**
   * Mark request as started
   */
  startRequest(): void {
    this.concurrentRequests++;
  }

  /**
   * Mark request as completed
   */
  endRequest(success: true, endpoint?: string): void;
  endRequest(success: false, endpoint?: string): void;
  endRequest(success: boolean, endpoint?: string): void {
    this.concurrentRequests--;

    if (!success && endpoint) {
      // Update circuit breaker
      const circuitBreakerKey = `${endpoint}_breaker`;
      const circuitBreaker = this.circuitBreakers.get(circuitBreakerKey) || {
        isOpen: false,
        failures: 0,
        lastFailure: 0,
      };

      circuitBreaker.failures++;
      circuitBreaker.lastFailure = Date.now();

      // Open circuit breaker after 5 failures
      if (circuitBreaker.failures >= 5) {
        circuitBreaker.isOpen = true;
        console.warn(`[LoadShedding] Circuit breaker opened for ${endpoint}`);
      }

      this.circuitBreakers.set(circuitBreakerKey, circuitBreaker);
    }
  }

  /**
   * Get current statistics
   */
  getStatistics(): LoadSheddingStats {
    const elapsed = (Date.now() - this.startTime) / 1000;
    const requestsPerSecond = this.totalRequests / elapsed;
    const rejectRatio = this.totalRequests > 0 ? this.rejectedRequests / this.totalRequests : 0;

    return {
      totalRequests: this.totalRequests,
      acceptedRequests: this.acceptedRequests,
      rejectedRequests: this.rejectedRequests,
      concurrentRequests: this.concurrentRequests,
      queueSize: 0, // Not implemented
      requestsPerSecond,
      rejectRatio,
    };
  }

  /**
   * Reset circuit breaker for endpoint
   */
  resetCircuitBreaker(endpoint: string): void {
    const circuitBreakerKey = `${endpoint}_breaker`;
    this.circuitBreakers.delete(circuitBreakerKey);
  }

  /**
   * Reset statistics
   */
  resetStatistics(): void {
    this.requestCounts.clear();
    this.totalRequests = 0;
    this.acceptedRequests = 0;
    this.rejectedRequests = 0;
    this.startTime = Date.now();
  }

  /**
   * Record accepted request
   */
  private acceptRequest(): void {
    this.acceptedRequests++;
  }

  /**
   * Record rejected request
   */
  private rejectRequest(): void {
    this.rejectedRequests++;
  }

  /**
   * Get priority for request type
   */
  static getPriority(type: RequestPriority['type']): RequestPriority {
    const priorities: Record<RequestPriority['type'], number> = {
      timer_update: 0, // Highest priority
      sync: 3,
      crud: 6,
      query: 9, // Lowest priority
    };

    return {
      level: priorities[type],
      type,
    };
  }
}

/**
 * Create a load shedding service instance
 */
export function createLoadSheddingService(config?: LoadSheddingConfig): LoadSheddingService {
  return new LoadSheddingService(config);
}

/**
 * Middleware factory for load shedding
 */
export function createLoadSheddingMiddleware(loadShedding: LoadSheddingService) {
  return function loadSheddingMiddleware(
    requestType: RequestPriority['type'],
    handler: () => Promise<unknown>
  ) {
    return async () => {
      const priority = LoadSheddingService.getPriority(requestType);

      if (!loadShedding.canAcceptRequest(priority)) {
        throw new Error('Service temporarily unavailable (rate limit exceeded)');
      }

      loadShedding.startRequest();

      try {
        const result = await handler();
        loadShedding.endRequest(true);
        return result;
      } catch (error) {
        loadShedding.endRequest(false, requestType);
        throw error;
      }
    };
  };
}
