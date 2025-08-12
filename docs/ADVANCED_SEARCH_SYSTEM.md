# Advanced Search System for Lobsters

This document describes the comprehensive search indexing system implemented for the Lobsters community platform. The system provides advanced search capabilities including full-text search, relevance scoring, faceted search, analytics, and real-time indexing.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Architecture](#architecture)
- [Installation & Setup](#installation--setup)
- [Usage](#usage)
- [API Endpoints](#api-endpoints)
- [Configuration](#configuration)
- [CLI Commands](#cli-commands)
- [Performance](#performance)
- [Analytics](#analytics)
- [Development](#development)

## 🔍 Overview

The advanced search system replaces the basic LIKE-based search with a comprehensive solution that includes:

- **Full-text indexing** with relevance scoring
- **Advanced query syntax** (phrases, boolean operators, wildcards)
- **Faceted search** with multiple filters
- **Real-time indexing** when content is created/updated
- **Search analytics** and trending queries
- **Intelligent suggestions** and autocomplete
- **Result highlighting** and ranking algorithms
- **Performance caching** for frequent searches

## ✨ Features

### 1. Advanced Query Syntax
- **Phrase search**: `"exact phrase"`
- **Must include**: `+required_term`
- **Must exclude**: `-excluded_term`
- **Wildcards**: `java*` or `*script`
- **Field filters**: `user:username`, `tag:security`

### 2. Comprehensive Search Types
- **Stories**: Title, description, URL, tags
- **Comments**: Comment text with confidence scoring
- **Users**: Username and about text

### 3. Intelligent Relevance Scoring
- Content relevance (exact vs partial matches)
- Engagement metrics (votes, comments)
- User reputation (karma)
- Recency boost for fresh content
- Domain and tag matching

### 4. Advanced Filters
- **Score range**: Minimum/maximum scores
- **Date range**: From/to dates
- **Tags**: Multiple tag filtering
- **Users**: Filter by specific users
- **Domain**: Filter by website domain
- **Content type**: Stories, comments, users

### 5. Search Analytics
- Popular and trending queries
- Search performance metrics
- Click-through rates
- Failed search tracking
- User behavior analysis

## 🏗️ Architecture

### Core Components

1. **SearchIndexService** (`src/Services/SearchIndexService.php`)
   - Handles content indexing and relevance scoring
   - Manages real-time updates and bulk operations
   - Provides faceted search capabilities

2. **SearchService** (`src/Services/SearchService.php`)
   - Enhanced with advanced query parsing
   - Implements ranking algorithms and highlighting
   - Integrates caching and analytics tracking

3. **SearchController** (`src/Controllers/SearchController.php`)
   - Provides web interface and API endpoints
   - Handles advanced filters and faceted search
   - Includes admin analytics endpoints

4. **SearchAnalytic** (`src/Models/SearchAnalytic.php`)
   - Tracks search queries and user interactions
   - Provides analytics and trending calculations

### Database Schema

- **search_index**: Full-text search index with metadata
- **search_analytics**: Search query tracking and analytics

## 🚀 Installation & Setup

### 1. Run Database Migrations

```bash
# Run the new search migrations
php bin/console migrate:run database/migrations/008_create_search_analytics_table.php
php bin/console migrate:run database/migrations/009_create_search_index_table.php
```

### 2. Build Initial Search Index

```bash
# Rebuild the entire search index
php bin/console search:index --rebuild

# Or build incrementally
php bin/console search:index --bulk-index=stories
php bin/console search:index --bulk-index=comments
php bin/console search:index --bulk-index=users
```

### 3. Configure Routes

Add the search routes to your application:

```php
// In your main routes file
require_once __DIR__ . '/../config/search_routes.php';
```

### 4. Update Dependency Injection

Ensure the new services are properly injected:

```php
// In your DI container configuration
$container->set(SearchIndexService::class, function() use ($container) {
    return new SearchIndexService($container->get(CacheService::class));
});

$container->set(SearchService::class, function() use ($container) {
    return new SearchService(
        $container->get(CacheService::class),
        $container->get(SearchIndexService::class)
    );
});
```

## 💻 Usage

### Basic Search

```
GET /search?q=security&what=stories&order=relevance
```

### Advanced Search with Filters

```
GET /search?q=web+development&what=all&order=relevance&min_score=5&tags=javascript,php&date_from=2024-01-01
```

### Faceted Search API

```
GET /search/faceted?query=python&type=stories&min_score=10&tags=programming
```

### Search Suggestions

```
GET /search/suggestions?q=java
```

## 🌐 API Endpoints

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/search` | Main search interface |
| GET | `/search/faceted` | Advanced faceted search |
| GET | `/search/suggestions` | Search suggestions |
| GET | `/search/autocomplete` | Legacy autocomplete |
| GET | `/search/popular` | Popular/trending searches |
| POST | `/search/track-click` | Track result clicks |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/search/analytics` | Search analytics |
| GET | `/admin/search/index/stats` | Index statistics |
| POST | `/admin/search/index/rebuild` | Rebuild search index |

### Example API Responses

#### Search Results
```json
{
  "results": [...],
  "highlighted_results": [...],
  "total": 150,
  "page": 1,
  "per_page": 20,
  "search_time_ms": 45.67,
  "parsed_query": {
    "terms": ["security"],
    "phrases": [],
    "filters": {}
  }
}
```

#### Search Analytics
```json
{
  "search_stats": {
    "total_searches": 12450,
    "unique_queries": 3200,
    "avg_results_count": 25.6,
    "click_through_rate": 15.8
  },
  "index_stats": {
    "total_documents": 45000,
    "by_type": {
      "story": 15000,
      "comment": 28000,
      "user": 2000
    }
  }
}
```

## ⚙️ Configuration

The search system is highly configurable through `/config/search_config.php`:

### Key Configuration Sections

- **Pagination**: Results per page, maximum results
- **Cache**: TTL for different cache types
- **Relevance Weights**: Scoring for different match types
- **Analytics**: Tracking and retention settings
- **Performance**: Query timeouts and rate limiting
- **Features**: Enable/disable specific functionality

### Example Configuration

```php
'relevance_weights' => [
    'title_exact' => 10.0,
    'title_partial' => 7.0,
    'description_exact' => 5.0,
    // ...
],

'cache' => [
    'search_results_ttl' => 600,
    'suggestions_ttl' => 3600,
    // ...
]
```

## 🛠️ CLI Commands

### Search Index Management

```bash
# Show comprehensive statistics
php bin/console search:index --stats --days=7

# Rebuild entire index
php bin/console search:index --rebuild

# Bulk index specific content type
php bin/console search:index --bulk-index=stories --offset=0 --limit=1000

# Clean up old data
php bin/console search:index --cleanup
```

### Example Output

```
Lobsters Search Index Management
================================

Index Statistics
----------------
Total Documents ............... 45,000
Stories ....................... 15,000
Comments ...................... 28,000
Users ......................... 2,000
Index Size .................... 125.6 MB
Last Updated .................. 2024-01-15 10:30:00

Search Analytics
----------------
Total Searches ................ 12,450
Unique Queries ................ 3,200
Avg Results per Search ........ 25.6
Click-through Rate ............ 15.8%
```

## ⚡ Performance

### Optimization Features

1. **Multi-level Caching**
   - Memory cache for frequent queries
   - File-based cache for search results
   - Suggestion caching

2. **Database Optimization**
   - Full-text indexes on content
   - Composite indexes for filtering
   - Query optimization

3. **Smart Relevance Scoring**
   - Efficient scoring algorithms
   - Weighted field matching
   - Engagement-based boosting

### Performance Monitoring

- Search time tracking
- Slow query logging
- Cache hit rates
- Index size monitoring

## 📊 Analytics

### Tracked Metrics

- **Search Volume**: Total searches over time
- **Query Popularity**: Most searched terms
- **Search Effectiveness**: Results count and click-through rates
- **User Behavior**: Search patterns and preferences
- **Performance**: Search times and optimization opportunities

### Available Analytics

1. **Popular Searches**: Most frequently searched terms
2. **Trending Searches**: Queries with recent popularity surge
3. **Failed Searches**: Queries that return no results
4. **Performance Metrics**: Average search times and result counts
5. **User Engagement**: Click-through rates and result interaction

## 🔧 Development

### Adding New Search Types

1. Update the `SearchIndexService` with new indexing logic
2. Extend the `SearchService` with type-specific search methods
3. Add configuration to `search_config.php`
4. Update API endpoints and validation

### Extending Relevance Scoring

1. Modify the `calculateRelevanceScore` method in `SearchIndexService`
2. Add new weights to the configuration
3. Update the engagement and recency multiplier calculations

### Custom Filters

1. Add filter extraction logic in `SearchController::extractFilters()`
2. Implement filter application in the respective search methods
3. Update the API documentation

### Testing

Create comprehensive tests for:
- Search functionality with various query types
- Relevance scoring accuracy
- Performance under load
- Analytics data integrity

## 🔮 Future Enhancements

1. **Machine Learning**: AI-powered relevance scoring
2. **Elasticsearch Integration**: For even better full-text search
3. **Personalization**: User-specific search customization
4. **Real-time Suggestions**: Live search-as-you-type
5. **Search Federation**: Search across multiple data sources
6. **Mobile Optimization**: Touch-friendly search interface

## 📞 Support

For questions or issues with the search system:

1. Check the logs for search-related errors
2. Review the configuration settings
3. Use CLI commands to diagnose index issues
4. Monitor search analytics for usage patterns

The search system is designed to be robust, scalable, and maintainable while providing users with a powerful and intuitive search experience.