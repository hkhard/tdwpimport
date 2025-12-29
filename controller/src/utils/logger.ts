/**
 * Structured Logging Utility
 * Implements T128: Comprehensive logging with log levels
 *
 * Features:
 * - Structured logging with JSON output
 * - Log levels: debug, info, warn, error
 * - Timestamp and context tracking
 * - Environment-aware (development vs production)
 */

export enum LogLevel {
  DEBUG = 0,
  INFO = 1,
  WARN = 2,
  ERROR = 3,
}

export interface LogContext {
  [key: string]: any;
}

interface LogEntry {
  level: string;
  message: string;
  timestamp: string;
  context?: LogContext;
  error?: {
    message: string;
    stack?: string;
    code?: string;
  };
}

class Logger {
  private minLevel: LogLevel;
  private isDevelopment: boolean;

  constructor() {
    this.isDevelopment = process.env.NODE_ENV === 'development';
    this.minLevel = this.isDevelopment ? LogLevel.DEBUG : LogLevel.INFO;
  }

  /**
   * Create log entry
   */
  private log(level: LogLevel, message: string, context?: LogContext, error?: Error): void {
    if (level < this.minLevel) {
      return;
    }

    const levelName = LogLevel[level];
    const entry: LogEntry = {
      level: levelName,
      message,
      timestamp: new Date().toISOString(),
      ...(context && { context }),
      ...(error && {
        error: {
          message: error.message,
          ...(error.stack && { stack: error.stack }),
          ...(error as any).code && { code: (error as any).code },
        },
      }),
    };

    // Output log
    if (this.isDevelopment) {
      this.formatConsole(entry);
    } else {
      // In production, output JSON for log aggregation
      console.log(JSON.stringify(entry));
    }
  }

  /**
   * Format log for console (development)
   */
  private formatConsole(entry: LogEntry): void {
    const { level, message, timestamp, context, error } = entry;
    const prefix = `[${timestamp}] [${level}]`;

    if (error) {
      console.error(prefix, message, error);
      if (error.stack) {
        console.error(error.stack);
      }
    } else if (context) {
      console.log(prefix, message, context);
    } else {
      console.log(prefix, message);
    }
  }

  /**
   * Debug level log
   */
  debug(message: string, context?: LogContext): void {
    this.log(LogLevel.DEBUG, message, context);
  }

  /**
   * Info level log
   */
  info(message: string, context?: LogContext): void {
    this.log(LogLevel.INFO, message, context);
  }

  /**
   * Warning level log
   */
  warn(message: string, context?: LogContext): void {
    this.log(LogLevel.WARN, message, context);
  }

  /**
   * Error level log
   */
  error(message: string, error?: Error, context?: LogContext): void {
    this.log(LogLevel.ERROR, message, context, error);
  }

  /**
   * Create child logger with default context
   */
  child(defaultContext: LogContext): Logger {
    const child = new Logger();
    child.debug = (message: string, context?: LogContext) =>
      this.debug(message, { ...defaultContext, ...context });
    child.info = (message: string, context?: LogContext) =>
      this.info(message, { ...defaultContext, ...context });
    child.warn = (message: string, context?: LogContext) =>
      this.warn(message, { ...defaultContext, ...context });
    child.error = (message: string, error?: Error, context?: LogContext) =>
      this.error(message, error, { ...defaultContext, ...context });
    return child;
  }
}

// Singleton instance
export const logger = new Logger();

// Export convenience functions
export const log = {
  debug: (message: string, context?: LogContext) => logger.debug(message, context),
  info: (message: string, context?: LogContext) => logger.info(message, context),
  warn: (message: string, context?: LogContext) => logger.warn(message, context),
  error: (message: string, error?: Error, context?: LogContext) => logger.error(message, error, context),
};
