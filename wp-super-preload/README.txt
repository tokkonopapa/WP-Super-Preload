=== WP Super Preload ===
Contributors: tokkonopapa
Donate link:
Tags: cache, caching, page cache, performance, speed, wp-super-cache, w3 total cache, hyper cache, quick cache
Requires at least: 3.1.0
Tested up to: 3.5.1
Stable tag: 0.9.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin helps to keep whole pages of your site always being cached 
in the fresh based on the sitemap.xml and your own settings.

== Description ==

You might use one of caching plugin such as [WP Super Cache][WSC], 
[W3 Total Cache][W3T], [Hyper Cache][HYP] or [Quick Cache][QIK] to make your 
site response fast.

These caching plugin has a process called 'garbage collection' which will 
remove old expired caches.

[Wikipedia][WIK] says:

*If requested data is contained in the cache (cache hit), this request can be 
served by simply reading the cache, which is comparatively faster.
Otherwise (cache miss), the data has to be recomputed or fetched from its 
original storage location, which is comparatively slower.*

This plugin keeps every page always being cached and improves the cache hit 
ratio. As a result, your visitors always feel your site so fast.

It crawls your site based on sitemap.xml (ex: [Google XML Sitemaps][GXS]) and 
selected pages such as front pages, fixed pages, categories, tags and so on, 
in order to keep page caches always being fresh.

Requires WordPress 3.1.0 or higher and PHP 5 with libcurl.

Features:

1. Preloading synchronized with garbage collection of your caching plugin.

2. Using 'curl_multi' PHP functions to crawl pages in parallel.
It makes expiration time aligned in a short period of time.

3. Split preloading is supported to reduce the load of your server.

Basic settings:

- **Trigger at the garbage collection WP-Cron event** :  
    To enable this plugin, select one of event to fit your caching plugin.

- **Trigger at the scheduled WP-Cron event** :  
    If you want an additional event, please select period.

- **URLs of sitemap** :  
    Typically, `http://example.com/sitemap.xml`.

- **Additional contents** :  
    Selection of additional pages that are not on the sitemap.

- **URLs of additional pages** :  
    Individual pages that are not on the sitemap.

- **Maximum number of pages** :  
    Limit the number of pages to be preloaded.

- **Additional UA string** :  
    Typically, `iPhone`.

Advanced settings:

- **Split preloading** :  
    Split the number of pages to reduce the server loads.

- **Number of requests per split** :  
    Maximum number of pages per each split.

- **Number of parallel requests** :  
    The number of pages to be fetched in parallel.

- **Interval between parallel requests** :  
    Interval in millisecond for parallel requests.

- **Test preloading** :  
    You can find the execution time and the number of pages that are preloaded.

== Installation ==

1. Upload `wp-super-preload` to the `/wp-content/plugins/` directory,
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= Is this a caching plugin ? =

No. This is a helper plugin for other caching plugins which generate page cache.

= How can I know this plugin works ? =

Please see the HTML of your pages and check timestamps that your caching plugin 
embedded.

= It seems not working. =

To synchronize preloading with garbage collection, you should set the WP-cron 
event name of your caching plugin. Please check on option settings page.

= I can't find my caching plugin in the list of WP-Cron event. =

Here is the built-in event list of the major plugins.

    Cache Plugin   | Event Name
    ---------------|------------------------------------------------
    WP Super Cache | `wp_cache_gc`
    W3 Total Cache | `w3_pgcache_cleanup`
    Hyper Cache    | `hyper_clean`
    Quick Cache    | `ws_plugin__qcache_garbage_collector__schedule`

When you use other plugin, you need to find the garbage collection event hook 
using [Cron View][CRV].

= Does this plugin support multi-site ? =

Unfortunately, no.

= How can I reduce the load of my server ? =

One solution is using `split preloading`.

If you have 1000 pages to be cached and the expiration time is 3600 seconds, 
you can set the garbage collection interval to 600 seconds (in your caching 
plugin's settings) and 200 pages to be requested in each garbage collection 
(in this plugin's settings).

Another solution is combining with some other plugins.

For example, [Widget Cache][WID] will work complementarily when generating 
preloaded page cache.

= Can I contribute to develop this plugin ? =

Of course, Yes. Please visit [WP-Super-Preload on Github][WSP].

== Screenshots ==

1. WP Super Preload Settings

== Changelog ==

= 1.0.0 =
* Initial release.

== Recommendation ==

- [WP-Cron Control][WCC]

== Similar plugins ==

- [AskApache Crazy Cache][ACC]
- [Warm Cache][WMC]
- [Generate Cache][GEN]

== Upgrade Notice ==

[GXS]: http://wordpress.org/extend/plugins/google-sitemap-generator/
[WSC]: http://wordpress.org/extend/plugins/wp-super-cache/
[W3T]: http://wordpress.org/extend/plugins/w3-total-cache/
[HYP]: http://wordpress.org/extend/plugins/hyper-cache/
[QIK]: http://wordpress.org/extend/plugins/quick-cache/
[CRV]: http://wordpress.org/extend/plugins/cron-view/
[WID]: http://wordpress.org/extend/plugins/wp-widget-cache/
[WSP]: https://github.com/tokkonopapa/WP-Super-Preload
[WCC]: http://wordpress.org/extend/plugins/wp-cron-control/
[ACC]: http://wordpress.org/extend/plugins/askapache-crazy-cache/
[WMC]: http://wordpress.org/extend/plugins/warm-cache/
[GEN]: http://wordpress.org/extend/plugins/generate-cache/
[WIK]: http://en.wikipedia.org/wiki/Cache_%28computing%29 "Cache (computing) - Wikipedia, the free encyclopedia"
