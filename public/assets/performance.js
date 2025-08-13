// Performance Optimization Module
// Includes lazy loading, infinite scroll, and other performance enhancements

class PerformanceManager {
    constructor() {
        this.init();
    }

    init() {
        // Initialize all performance features
        this.initLazyLoading();
        this.initInfiniteScroll();
        this.initImageOptimization();
        this.initDeferredLoading();
        this.initVirtualScrolling();
        this.initResourcePreloading();
        this.initPerformanceMonitoring();
    }

    // Lazy Loading Implementation
    initLazyLoading() {
        // Use Intersection Observer for modern browsers
        if ('IntersectionObserver' in window) {
            this.lazyLoadIntersectionObserver();
        } else {
            // Fallback for older browsers
            this.lazyLoadScrollListener();
        }
    }

    lazyLoadIntersectionObserver() {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    this.loadImage(img);
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px'
        });

        // Observe all images with data-src attribute
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });

        // Observe all background images
        document.querySelectorAll('[data-bg]').forEach(element => {
            imageObserver.observe(element);
        });
    }

    lazyLoadScrollListener() {
        const images = document.querySelectorAll('img[data-src], [data-bg]');
        
        const loadImagesInViewport = () => {
            images.forEach(element => {
                if (this.isInViewport(element)) {
                    this.loadImage(element);
                }
            });
        };

        // Throttled scroll listener
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            if (scrollTimeout) {
                cancelAnimationFrame(scrollTimeout);
            }
            scrollTimeout = requestAnimationFrame(loadImagesInViewport);
        });

        // Initial load
        loadImagesInViewport();
    }

    loadImage(element) {
        if (element.tagName === 'IMG') {
            const src = element.dataset.src;
            if (src) {
                element.src = src;
                element.removeAttribute('data-src');
                element.classList.add('loaded');
            }
        } else if (element.dataset.bg) {
            element.style.backgroundImage = `url(${element.dataset.bg})`;
            element.removeAttribute('data-bg');
            element.classList.add('loaded');
        }
    }

    isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    // Infinite Scroll for Story Lists
    initInfiniteScroll() {
        const storyContainer = document.querySelector('.stories-list');
        if (!storyContainer) return;

        let page = 1;
        let loading = false;
        let hasMore = true;

        const loadMoreStories = async () => {
            if (loading || !hasMore) return;

            loading = true;
            this.showLoadingIndicator();

            try {
                const response = await fetch(`/api/v1/stories?page=${page + 1}&limit=20`);
                const data = await response.json();

                if (data.success && data.data.length > 0) {
                    this.appendStories(data.data);
                    page++;
                    hasMore = data.data.length === 20; // Assuming 20 per page
                } else {
                    hasMore = false;
                }
            } catch (error) {
                console.error('Error loading more stories:', error);
                hasMore = false;
            } finally {
                loading = false;
                this.hideLoadingIndicator();
            }
        };

        // Use Intersection Observer to trigger loading
        const sentinel = document.createElement('div');
        sentinel.className = 'scroll-sentinel';
        storyContainer.appendChild(sentinel);

        if ('IntersectionObserver' in window) {
            const scrollObserver = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    loadMoreStories();
                }
            }, {
                rootMargin: '200px'
            });

            scrollObserver.observe(sentinel);
        }
    }

    appendStories(stories) {
        const container = document.querySelector('.stories-list');
        if (!container) return;

        stories.forEach(story => {
            const storyElement = this.createStoryElement(story);
            container.appendChild(storyElement);
        });

        // Re-initialize lazy loading for new images
        this.initLazyLoading();
    }

    createStoryElement(story) {
        const element = document.createElement('div');
        element.className = 'story-item';
        element.innerHTML = `
            <div class="story-meta">
                <div class="story-voting">
                    <button class="vote-up" data-story-id="${story.id}" data-vote="up">▲</button>
                    <span class="score">${story.score || 0}</span>
                    <button class="vote-down" data-story-id="${story.id}" data-vote="down">▼</button>
                </div>
            </div>
            <div class="story-content">
                <h3><a href="/s/${story.id}/${story.slug || ''}">${story.title}</a></h3>
                <div class="story-details">
                    <span class="story-domain">${story.domain || ''}</span>
                    <span class="story-time">${story.created_at}</span>
                    <span class="story-comments">${story.comment_count || 0} comments</span>
                </div>
            </div>
        `;
        return element;
    }

    showLoadingIndicator() {
        const indicator = document.querySelector('.loading-indicator') || 
                         this.createLoadingIndicator();
        indicator.style.display = 'block';
    }

    hideLoadingIndicator() {
        const indicator = document.querySelector('.loading-indicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }

    createLoadingIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'loading-indicator';
        indicator.innerHTML = `
            <div class="loading-spinner"></div>
            <span>Loading more stories...</span>
        `;
        document.body.appendChild(indicator);
        return indicator;
    }

    // Image Optimization
    initImageOptimization() {
        // Add responsive image support
        this.addResponsiveImageSupport();
        
        // Add image error handling
        this.addImageErrorHandling();
        
        // Add image placeholder support
        this.addImagePlaceholders();
    }

    addResponsiveImageSupport() {
        document.querySelectorAll('img[data-srcset]').forEach(img => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.srcset) {
                            img.srcset = img.dataset.srcset;
                            img.removeAttribute('data-srcset');
                        }
                        observer.unobserve(img);
                    }
                });
            });
            observer.observe(img);
        });
    }

    addImageErrorHandling() {
        document.addEventListener('error', (e) => {
            if (e.target.tagName === 'IMG') {
                e.target.src = '/assets/icons/placeholder.png';
                e.target.classList.add('image-error');
            }
        }, true);
    }

    addImagePlaceholders() {
        document.querySelectorAll('img[data-src]').forEach(img => {
            if (!img.src) {
                img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIwIiBoZWlnaHQ9IjI0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkxvYWRpbmcuLi48L3RleHQ+PC9zdmc+';
                img.classList.add('lazy-placeholder');
            }
        });
    }

    // Deferred Loading for Non-Critical Resources
    initDeferredLoading() {
        // Defer loading of non-critical CSS
        this.deferNonCriticalCSS();
        
        // Defer loading of analytics and tracking scripts
        this.deferAnalytics();
        
        // Defer loading of social media widgets
        this.deferSocialWidgets();
    }

    deferNonCriticalCSS() {
        const deferredStyles = document.querySelectorAll('link[data-defer]');
        deferredStyles.forEach(link => {
            const href = link.dataset.defer;
            if (href) {
                window.addEventListener('load', () => {
                    const newLink = document.createElement('link');
                    newLink.rel = 'stylesheet';
                    newLink.href = href;
                    document.head.appendChild(newLink);
                });
            }
        });
    }

    deferAnalytics() {
        window.addEventListener('load', () => {
            // Load analytics after page load
            const analytics = document.querySelector('[data-analytics]');
            if (analytics) {
                // Initialize analytics here
                console.log('Analytics loaded deferred');
            }
        });
    }

    deferSocialWidgets() {
        // Defer social media widgets until user interaction
        const socialWidgets = document.querySelectorAll('[data-social-widget]');
        socialWidgets.forEach(widget => {
            widget.addEventListener('mouseenter', () => {
                this.loadSocialWidget(widget);
            }, { once: true });
        });
    }

    loadSocialWidget(widget) {
        const type = widget.dataset.socialWidget;
        // Load specific social widget based on type
        console.log(`Loading ${type} widget`);
    }

    // Virtual Scrolling for Large Lists
    initVirtualScrolling() {
        const virtualLists = document.querySelectorAll('[data-virtual-scroll]');
        virtualLists.forEach(list => {
            this.setupVirtualScrolling(list);
        });
    }

    setupVirtualScrolling(container) {
        const itemHeight = parseInt(container.dataset.itemHeight) || 50;
        const bufferSize = parseInt(container.dataset.bufferSize) || 10;
        
        let startIndex = 0;
        let endIndex = 0;
        
        const updateVisibleItems = () => {
            const scrollTop = container.scrollTop;
            const containerHeight = container.clientHeight;
            
            startIndex = Math.max(0, Math.floor(scrollTop / itemHeight) - bufferSize);
            endIndex = Math.min(
                this.totalItems,
                Math.ceil((scrollTop + containerHeight) / itemHeight) + bufferSize
            );
            
            this.renderVirtualItems(container, startIndex, endIndex);
        };
        
        container.addEventListener('scroll', this.throttle(updateVisibleItems, 16));
        updateVisibleItems();
    }

    renderVirtualItems(container, start, end) {
        // Implementation depends on data source
        // This is a basic framework for virtual scrolling
        console.log(`Rendering virtual items ${start} to ${end}`);
    }

    // Resource Preloading
    initResourcePreloading() {
        // Preload critical resources
        this.preloadCriticalResources();
        
        // Preload on hover for improved perceived performance
        this.preloadOnHover();
        
        // DNS prefetch for external domains
        this.addDNSPrefetch();
    }

    preloadCriticalResources() {
        const criticalResources = [
            '/assets/application.css',
            '/assets/application.js',
            '/assets/icons/icon-192x192.png'
        ];
        
        criticalResources.forEach(resource => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = this.getResourceType(resource);
            link.href = resource;
            document.head.appendChild(link);
        });
    }

    preloadOnHover() {
        document.addEventListener('mouseover', (e) => {
            if (e.target.tagName === 'A') {
                const href = e.target.href;
                if (href && !this.isPreloaded(href)) {
                    this.preloadPage(href);
                }
            }
        });
    }

    preloadPage(url) {
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        document.head.appendChild(link);
        
        // Mark as preloaded to avoid duplicates
        this.markAsPreloaded(url);
    }

    isPreloaded(url) {
        return document.querySelector(`link[href="${url}"]`) !== null;
    }

    markAsPreloaded(url) {
        // Implementation for tracking preloaded URLs
    }

    addDNSPrefetch() {
        const externalDomains = [
            'fonts.googleapis.com',
            'cdnjs.cloudflare.com'
        ];
        
        externalDomains.forEach(domain => {
            const link = document.createElement('link');
            link.rel = 'dns-prefetch';
            link.href = `//${domain}`;
            document.head.appendChild(link);
        });
    }

    getResourceType(url) {
        if (url.endsWith('.css')) return 'style';
        if (url.endsWith('.js')) return 'script';
        if (url.match(/\.(png|jpg|jpeg|gif|webp)$/)) return 'image';
        if (url.endsWith('.woff2')) return 'font';
        return 'fetch';
    }

    // Performance Monitoring
    initPerformanceMonitoring() {
        // Monitor Core Web Vitals
        this.monitorCoreWebVitals();
        
        // Monitor resource loading
        this.monitorResourceLoading();
        
        // Monitor user interactions
        this.monitorUserInteractions();
    }

    monitorCoreWebVitals() {
        if ('PerformanceObserver' in window) {
            // Check supported entry types first
            const supportedEntryTypes = PerformanceObserver.supportedEntryTypes || [];
            
            // Largest Contentful Paint (LCP)
            if (supportedEntryTypes.includes('largest-contentful-paint')) {
                try {
                    new PerformanceObserver((entryList) => {
                        const entries = entryList.getEntries();
                        const lastEntry = entries[entries.length - 1];
                        console.log('LCP:', lastEntry.startTime);
                    }).observe({ entryTypes: ['largest-contentful-paint'] });
                } catch (e) {
                    console.log('LCP monitoring failed:', e.message);
                }
            }

            // First Input Delay (FID)
            if (supportedEntryTypes.includes('first-input')) {
                try {
                    new PerformanceObserver((entryList) => {
                        entryList.getEntries().forEach(entry => {
                            console.log('FID:', entry.processingStart - entry.startTime);
                        });
                    }).observe({ entryTypes: ['first-input'] });
                } catch (e) {
                    console.log('FID monitoring failed:', e.message);
                }
            }

            // Cumulative Layout Shift (CLS)
            if (supportedEntryTypes.includes('layout-shift')) {
                try {
                    new PerformanceObserver((entryList) => {
                        let cumulativeScore = 0;
                        entryList.getEntries().forEach(entry => {
                            if (!entry.hadRecentInput) {
                                cumulativeScore += entry.value;
                            }
                        });
                        console.log('CLS:', cumulativeScore);
                    }).observe({ entryTypes: ['layout-shift'] });
                } catch (e) {
                    console.log('CLS monitoring failed:', e.message);
                }
            }
        }
    }

    monitorResourceLoading() {
        window.addEventListener('load', () => {
            if ('performance' in window) {
                const navigation = performance.getEntriesByType('navigation')[0];
                console.log('Page Load Time:', navigation.loadEventEnd - navigation.loadEventStart);
                
                const resources = performance.getEntriesByType('resource');
                resources.forEach(resource => {
                    if (resource.duration > 1000) { // Log slow resources
                        console.log('Slow resource:', resource.name, resource.duration);
                    }
                });
            }
        });
    }

    monitorUserInteractions() {
        // Track time to first interaction
        let firstInteraction = false;
        const interactionEvents = ['click', 'keydown', 'scroll'];
        
        interactionEvents.forEach(event => {
            document.addEventListener(event, () => {
                if (!firstInteraction) {
                    firstInteraction = true;
                    console.log('Time to First Interaction:', performance.now());
                }
            }, { once: true });
        });
    }

    // Utility Methods
    throttle(func, delay) {
        let timeoutId;
        let lastExecTime = 0;
        return function (...args) {
            const currentTime = Date.now();
            
            if (currentTime - lastExecTime > delay) {
                func.apply(this, args);
                lastExecTime = currentTime;
            } else {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    func.apply(this, args);
                    lastExecTime = Date.now();
                }, delay - (currentTime - lastExecTime));
            }
        };
    }

    debounce(func, delay) {
        let timeoutId;
        return function (...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func.apply(this, args), delay);
        };
    }
}

// Initialize Performance Manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.performanceManager = new PerformanceManager();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PerformanceManager;
}