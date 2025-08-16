# Assurify Platform

[![Build Status](https://github.com/assurify/platform/actions/workflows/check.yml/badge.svg)](https://github.com/assurify/platform/actions/workflows/check.yml)

**Assurify** is an advanced community platform built with modern PHP using the Slim framework, featuring enterprise-grade security and performance optimizations. Originally ported from the open-source Lobsters Rails codebase, Assurify has been extensively enhanced with:

## 🚀 Key Features

### Core Platform
- **Modern PHP Architecture**: Built on Slim 4 framework with PSR standards
- **Advanced Security**: Multi-layered security with rate limiting, threat detection, and encryption
- **High Performance**: Optimized caching, database queries, and real-time features
- **Progressive Web App**: Full PWA support with offline capabilities
- **Real-time Features**: WebSocket integration for live updates

### Security & Compliance
- **Multi-Factor Authentication**: TOTP, SMS, and email-based MFA
- **Advanced Rate Limiting**: Intelligent throttling with ML-based anomaly detection
- **Content Moderation**: AI-powered spam and abuse prevention
- **Audit Logging**: Comprehensive audit trails with compliance reporting (GDPR, SOX, HIPAA)
- **Encryption**: End-to-end encryption for data storage and communication
- **Vulnerability Scanning**: Built-in security testing and penetration testing tools

### Enterprise Features
- **IP Security**: Geolocation filtering and VPN/proxy detection
- **Backup & Recovery**: Automated backup systems with disaster recovery testing
- **Performance Monitoring**: Real-time performance analytics and Core Web Vitals tracking
- **Admin Dashboard**: Comprehensive administration interface with analytics

## 🛠 Technology Stack

- **Backend**: PHP 8.1+, Slim 4 Framework
- **Database**: MySQL/MariaDB with Eloquent ORM
- **Frontend**: Progressive Web App with service workers
- **Security**: Industry-standard encryption (AES-256-GCM)
- **Caching**: Redis/Memcached support
- **Real-time**: WebSocket integration
- **Testing**: PHPUnit with comprehensive test coverage

## 📋 Requirements

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Composer for dependency management
- Redis (recommended for caching)
- Node.js (for frontend build tools)

## 🚀 Quick Start

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/assurify/platform.git
   cd platform
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database and security settings
   ```

4. **Set up database**
   ```bash
   php setup_db.php
   ```

5. **Start development server**
   ```bash
   php -S localhost:8080 -t public
   ```

### Docker Setup

```bash
docker-compose up -d
```

## 📰 Story Views & Navigation

Assurify provides multiple curated views for discovering and engaging with content, each designed for different browsing patterns and user needs:

### 🏠 **Home** (`/`)
The main landing page featuring **score-based ranking** of stories. Stories are sorted by popularity (upvotes) with recent submissions given priority. This provides a balanced view of both popular and fresh content, making it ideal for users who want to see what the community values most.

**Sorting Algorithm**: `ORDER BY score DESC, created_at DESC`

### 🔥 **Hot** (`/hot`) 
An explicit **popularity-focused view** identical to Home, available as a direct route but not displayed in main navigation. This provides programmatic access to the same hot stories algorithm.

**Sorting Algorithm**: `ORDER BY score DESC, created_at DESC`

### ⚡ **Active** (`/active`)
Shows stories with **recent discussion activity**, highlighting conversations that are happening right now. This view is perfect for users who want to jump into ongoing discussions and see what topics are generating engagement, regardless of the story's original score.

**Sorting Algorithm**: `ORDER BY last_comment_at DESC`

### 🆕 **Recent** (`/recent`)
Displays **new submissions from the past 14 days**, ordered chronologically. Unlike other views, this includes all recent stories regardless of their popularity, making it ideal for discovering fresh content and ensuring new submissions get visibility. This view helps maintain community engagement with emerging topics.

**Sorting Algorithm**: `ORDER BY created_at DESC` (filtered to last 14 days)

### ⭐ **Newest** (`/newest`)
Pure **chronological listing** of all stories by submission time. This view provides the most recent submissions first and is essential for moderators, active community members, and users who want to see everything as it's submitted without any popularity or activity filtering.

**Sorting Algorithm**: `ORDER BY created_at DESC`

### 🏆 **Top** (`/top/{duration}`)
**Time-filtered popularity rankings** showing the highest-scored stories within specific time periods. Users can view top stories from the past day, week, month, or year, making it easy to discover the best content from any time period.

**Available Durations**: 
- Days: `1d` to `90d` (e.g., `/top/7d` for past week)
- Weeks: `1w` to `52w` (e.g., `/top/1w` for past week)  
- Months: `1m` to `24m` (e.g., `/top/1m` for past month)
- Years: `1y` to `10y` (e.g., `/top/1y` for past year)

**Sorting Algorithm**: `ORDER BY score DESC, created_at DESC` (within date range)

### 🎯 **Navigation Design Philosophy**

The main navigation (`Home | Active | Recent | Newest | Top`) serves different user behaviors:
- **Casual browsers**: Home for quality content  
- **Active participants**: Active for joining ongoing discussions  
- **Content discoverers**: Recent and Newest for fresh submissions
- **Archival exploration**: Top for historical best content

Each view respects user tag preferences and filtering settings, ensuring a personalized experience while maintaining the distinct character of each browsing mode. The Hot route remains available for direct access but is not featured in navigation to avoid redundancy with Home.

## 🔧 Configuration

### Security Configuration

Assurify includes comprehensive security features that can be configured via environment variables:

```env
# Master encryption key (generate with: openssl rand -hex 32)
ENCRYPTION_MASTER_KEY=your-secure-master-key

# Database configuration
DB_HOST=localhost
DB_DATABASE=assurify
DB_USERNAME=your-username
DB_PASSWORD=your-password

# Security settings
AUTH_VALIDATE_IP=true
ALLOWED_COUNTRIES=US,CA,GB,DE
RATE_LIMIT_ENABLED=true

# Backup configuration
BACKUP_ENABLED=true
BACKUP_ENCRYPTION=true
```

### Performance Configuration

```env
# Caching
CACHE_DRIVER=redis
REDIS_HOST=localhost
REDIS_PORT=6379

# Performance monitoring
PERFORMANCE_MONITORING=true
AUDIT_LOGGING=true
```

## 📖 Documentation

- **[Security Architecture](docs/SECURITY_ARCHITECTURE.md)** - Comprehensive security framework
- **[API Documentation](src/Views/api/docs.php)** - REST API reference
- **[Contributing Guidelines](CONTRIBUTING.md)** - How to contribute
- **[Deployment Guide](docs/deployment.md)** - Production deployment

## 🔒 Security Features

### Authentication & Authorization
- Role-based access control (RBAC)
- Multi-factor authentication (MFA)
- Session management with security features
- Account lockout and brute force protection

### Data Protection
- AES-256-GCM encryption for sensitive data
- Secure key management and rotation
- CSRF protection with token validation
- XSS prevention with content security policies

### Monitoring & Compliance
- Real-time security monitoring
- Audit logging with forensic capabilities
- Compliance reporting (GDPR, SOX, HIPAA, PCI DSS)
- Vulnerability scanning and penetration testing

### Infrastructure Security
- IP-based security and geolocation filtering
- Rate limiting with adaptive throttling
- DDoS protection and traffic analysis
- Automated backup and disaster recovery

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Philosophy

- **Security First**: Every feature is designed with security as a primary concern
- **Performance Optimized**: Built for scale with efficient algorithms and caching
- **Standards Compliant**: Follows PSR standards and best practices
- **Enterprise Ready**: Production-ready with comprehensive monitoring and logging

### Code Standards

- PSR-12 coding standards
- Comprehensive PHPUnit test coverage
- Security-focused code reviews
- Performance benchmarking for all features

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Originally based on the open-source [Lobsters](https://github.com/lobsters/lobsters) Rails codebase
- Enhanced with modern PHP practices and enterprise security features
- Built with contributions from the security and PHP communities

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/assurify/platform/issues)
- **Documentation**: [Wiki](https://github.com/assurify/platform/wiki)
- **Security**: For security issues, please email security@assurify.com

---

**Assurify Platform** - Secure, scalable, and feature-rich community platform for the modern web.