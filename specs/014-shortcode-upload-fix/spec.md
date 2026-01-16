# Feature Specification: Frontend Tournament Import Shortcode Fix

**Feature Branch**: `014-shortcode-upload-fix`
**Created**: 2025-01-06
**Status**: Draft
**Input**: User description: "the tdwp_tournament_import shortcode deosn't work in uploading a .tdt file when I add it as shortcode to a page. We need to fix this."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Frontend Tournament File Upload (Priority: P1)

As a tournament director with appropriate permissions, I want to upload .tdt files directly from a frontend page so that I can import tournament results without accessing the WordPress admin dashboard.

**Why this priority**: This is the core functionality of the shortcode. Without it, users cannot import tournaments from the frontend, which is the primary use case for this feature.

**Independent Test**: Can be fully tested by adding `[tdwp_tournament_import]` shortcode to a page, attempting to upload a valid .tdt file, and verifying the tournament is created in WordPress.

**Acceptance Scenarios**:

1. **Given** I am logged in with `edit_posts` permission, **When** I add the shortcode to a page and upload a valid .tdt file, **Then** the tournament should be imported successfully and a success message displayed
2. **Given** I am not logged in, **When** I visit a page with the shortcode, **Then** I should see a message asking me to log in
3. **Given** I am logged in but lack `edit_posts` permission, **When** I visit the page with the shortcode, **Then** I should see a message indicating insufficient permissions
4. **Given** I attempt to upload a non-.tdt file, **When** I select the file and submit, **Then** I should see an error message indicating invalid file type

---

### User Story 2 - JavaScript Dependency Loading (Priority: P1)

As a tournament director using the frontend import form, I expect the file upload functionality to work immediately when the page loads, without requiring me to refresh or troubleshoot browser console errors.

**Why this priority**: The upload form depends on jQuery for AJAX requests. If jQuery is not loaded, the form cannot function, making this a critical dependency issue.

**Independent Test**: Can be tested by adding the shortcode to a page and checking the browser console for jQuery errors when the page loads.

**Acceptance Scenarios**:

1. **Given** I visit a page with the import shortcode, **When** the page loads, **Then** jQuery should be available (no `$ is not defined` errors in console)
2. **Given** the form has loaded, **When** I select a .tdt file and click Import, **Then** the AJAX request should execute without JavaScript errors

---

### User Story 3 - User Feedback During Upload (Priority: P2)

As a tournament director uploading a file, I want to see clear feedback during the upload process so that I know the system is working and can understand any errors that occur.

**Why this priority**: Important for user experience but not critical. The system can function without polished feedback, though it would be frustrating.

**Independent Test**: Can be tested by uploading files and observing the status messages and error handling.

**Acceptance Scenarios**:

1. **Given** I am uploading a file, **When** the upload is in progress, **Then** I should see "Uploading..." status message
2. **Given** the upload succeeds, **When** the process completes, **Then** I should see a success message and the form should reset
3. **Given** the upload fails, **When** an error occurs, **Then** I should see an error message with details

---

### Edge Cases

- What happens when a user uploads a .tdt file that is corrupted or malformed?
- What happens when the file size exceeds WordPress upload limits?
- What happens when multiple users attempt to upload simultaneously?
- What happens when the user's session expires during file upload?
- What happens when the AJAX request times out (slow server/network)?
- What happens when WordPress is in maintenance mode?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The shortcode MUST check if the user is logged in before displaying the upload form
- **FR-002**: The shortcode MUST verify the user has `edit_posts` capability before allowing imports
- **FR-003**: The shortcode MUST load jQuery as a dependency on frontend pages where the shortcode appears
- **FR-004**: The upload form MUST accept only .tdt files (Tournament Director format)
- **FR-005**: The form MUST validate file extension on the client side before attempting upload
- **FR-006**: The system MUST send the file via AJAX to WordPress admin-ajax.php endpoint
- **FR-007**: The AJAX request MUST include a valid WordPress nonce for security verification
- **FR-008**: The system MUST display success/error messages to the user after upload completes
- **FR-009**: The system MUST support an option to publish the tournament immediately or save as draft
- **FR-010**: The system MUST reset the form after a successful upload
- **FR-011**: The shortcode MUST provide appropriate fallback messages for users without permissions

### Key Entities

- **Tournament File**: .tdt format file containing tournament results from Tournament Director software
- **Upload Form**: Frontend form with file input, publish checkbox, and submit button
- **AJAX Request**: Asynchronous HTTP POST to WordPress admin-ajax.php with file data and nonce
- **Nonce**: WordPress security token to prevent CSRF attacks
- **Permission Context**: User must be logged in with `edit_posts` capability

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can successfully import tournaments via frontend shortcode without accessing WordPress admin
- **SC-002**: File upload completes within 30 seconds for files under 5MB on standard hosting
- **SC-003**: 100% of upload attempts show clear success or error messages (no silent failures)
- **SC-004**: Zero JavaScript console errors related to missing dependencies when page loads
- **SC-005**: All permission checks prevent unauthorized uploads (no bypass possible)
- **SC-006**: Valid .tdt files are successfully parsed and imported 95% of the time (failures only for truly malformed files)
- **SC-007**: The shortcode works on any WordPress theme without conflicts

## Assumptions

1. jQuery is the appropriate JavaScript library for AJAX requests (already used in WordPress core)
2. WordPress admin-ajax.php is the correct endpoint for frontend AJAX requests
3. The existing AJAX handler `ajax_frontend_import_tournament` functions correctly once the JavaScript dependency issue is resolved
4. Standard WordPress upload limits (e.g., 2MB-10MB) are acceptable for .tdt files
5. The .tdt parser already handles validation of file contents (file extension check is sufficient pre-validation)
6. Users accessing the shortcode have basic WordPress user accounts (not anonymous visitors)

## Constraints

1. **WordPress Standards**: Must follow WordPress coding standards and security practices
2. **Backward Compatibility**: Must not break existing tournament import functionality in admin dashboard
3. **Theme Independence**: Must work across different WordPress themes without conflicts
4. **Performance**: Script loading should not significantly impact page load times
5. **Security**: All AJAX requests must include proper nonce verification
