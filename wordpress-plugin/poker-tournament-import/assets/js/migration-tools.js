/**
 * Migration Tools Admin JavaScript
 *
 * @package Poker Tournament Import
 */

// Clear migration status from URL after 3 seconds
setTimeout(function() {
    const url = new URL(window.location);
    url.searchParams.delete("migration_status");
    url.searchParams.delete("action");
    window.history.replaceState({}, document.title, url.href);
}, 3000);
