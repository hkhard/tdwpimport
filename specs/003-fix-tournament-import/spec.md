# Feature Specification: Fix Tournament Import Button and Public Import Function

**Feature Branch**: `003-fix-tournament-import`
**Created**: 2025-12-29
**Status**: Draft
**Input**: User description: "in the TDWP wordpress plugin, we have two issues where 1. the tournament import button has disappeared from the wordpress dashboard. 2. the import tournament function on the public facing statistics page is giving an error on upload and no tournament result is published."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Restore Admin Import Button (Priority: P1)

As a WordPress administrator, I need to access the tournament import button from the WordPress dashboard so that I can upload Tournament Director (.tdt) files and import tournament results into the system.

**Why this priority**: This is a critical regression that completely blocks administrators from importing tournaments. Without this functionality, the core purpose of the plugin cannot be fulfilled.

**Independent Test**: Can be fully tested by logging into WordPress admin dashboard, navigating to the Poker Tournaments section, and verifying the import button is visible and functional on the appropriate admin page.

**Acceptance Scenarios**:

1. **Given** I am logged into WordPress as an administrator, **When** I navigate to the Poker Tournaments → Import Tournament page, **Then** I should see an import button or form that allows me to upload .tdt files
2. **Given** the import button is visible, **When** I click on it or interact with the import form, **Then** the import interface should load without errors
3. **Given** I am on a different admin page, **When** I look for the Poker Tournaments menu, **Then** it should be visible in the WordPress admin sidebar

---

### User Story 2 - Fix Public Statistics Page Import (Priority: P1)

As a tournament participant or visitor, I need to be able to use the import function on the public-facing statistics page so that I can upload tournament results without requiring admin access.

**Why this priority**: This is a critical regression that prevents public users from importing tournaments. The public import function is a key feature for allowing tournament directors to import results without needing WordPress admin credentials.

**Independent Test**: Can be fully tested by visiting the public-facing statistics page on the frontend, attempting to upload a .tdt file through the public import form, and verifying that the tournament is successfully imported and published.

**Acceptance Scenarios**:

1. **Given** I am a visitor on the public statistics page, **When** I locate the tournament import section or form, **Then** I should be able to see a file upload interface for .tdt files
2. **Given** the import form is visible, **When** I select a valid .tdt file and submit the form, **Then** the file should upload successfully without errors and a new tournament should be created
3. **Given** I have uploaded a .tdt file, **When** the import process completes, **Then** I should see a success message or confirmation that the tournament was imported and published
4. **Given** I attempt to upload an invalid file, **When** the system processes the upload, **Then** I should see a helpful error message explaining what went wrong

---

### Edge Cases

- What happens when a user without appropriate permissions tries to access the admin import button?
- How does the system handle malformed or corrupted .tdt files during upload?
- What happens if the file upload exceeds PHP's maximum file size limits?
- How does the system behave when multiple users attempt to import tournaments simultaneously?
- What happens if the database connection fails during the import process?
- How are non-Latin characters or special encoding handled in .tdt file names and content?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The WordPress admin dashboard MUST display a tournament import button or interface on the appropriate Poker Tournaments admin page
- **FR-002**: The admin import button MUST be accessible only to users with appropriate WordPress capabilities (typically 'manage_options' or custom tournament management capability)
- **FR-003**: The public-facing statistics page MUST provide an import function that allows file uploads for .tdt files
- **FR-004**: The public import function MUST validate uploaded files before processing
- **FR-005**: When a .tdt file is uploaded via the public import function, the system MUST parse the file and create a tournament post
- **FR-006**: The import process MUST publish the tournament result automatically or according to configured settings
- **FR-007**: The system MUST display appropriate success or error messages to users after an import attempt
- **FR-008**: Error messages MUST be user-friendly and provide actionable guidance
- **FR-009**: The system MUST log import attempts, failures, and errors for troubleshooting purposes
- **FR-010**: The import function MUST handle WordPress nonce verification for security

### Key Entities

- **Tournament Import Request**: Represents a user's request to import a tournament, including the uploaded file, user identity, timestamp, and processing status
- **Import Error Log**: Records details of failed import attempts including error type, file information, stack trace, and user context
- **Tournament Post**: The WordPress custom post type created after successful import, containing parsed tournament data from the .tdt file

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can access the tournament import button within 2 clicks from the WordPress dashboard
- **SC-002**: 100% of valid .tdt file uploads through the public import function result in successfully created and published tournament posts
- **SC-003**: Import error messages are clear and actionable, reducing support requests by 80%
- **SC-004**: The import function completes processing within 30 seconds for standard tournament files (up to 500 players)
- **SC-005**: Both admin and public import functions handle concurrent import attempts without data corruption

## Dependencies & Assumptions

### Dependencies

- WordPress admin menu system is functioning correctly
- Custom post type 'tournament' is registered and active
- File upload capabilities are enabled in WordPress configuration
- PHP settings allow for file uploads of sufficient size

### Assumptions

- The tournament import button previously existed and functioned correctly before a recent change
- The public import function is intended to be available to non-admin users
- The .tdt file parser is functional and correctly processes valid tournament files
- Standard WordPress roles and capabilities are in use
- The plugin follows WordPress security best practices including nonce verification
