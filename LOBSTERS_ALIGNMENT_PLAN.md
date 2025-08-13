# Lobsters Alignment Plan
## Complete Application Audit and Phased Implementation

### Current State Analysis

#### ✅ What We Have (Working)
- Basic user authentication (login/logout)
- Story submission and display
- Basic user profiles
- Navigation structure aligned
- Comments display (but not creation)
- Search page structure
- Database schema (partial)

#### ❌ Major Missing Features

**Core Interaction Features:**
- Story voting (upvote/downvote)
- Comment creation and voting  
- Story flagging/reporting
- User karma system
- Comment threading/replies

**User Features:**
- Private messaging
- User settings/preferences
- User avatars
- Hat system (badges/flairs)
- Saved stories
- Read ribbons (tracking read posts)

**Moderation Features:**
- Moderation interface
- Ban system
- Domain banning
- Story/comment flagging
- Moderation logs

**Social Features:**
- Following users
- Invitation system (partially implemented)
- User profiles with activity

### Database Schema Alignment

#### Missing Core Tables:
1. `hats` - User badges/flairs
2. `hat_requests` - Badge requests
3. `messages` - Private messaging
4. `saved_stories` - User saved stories
5. `read_ribbons` - Read tracking
6. `moderations` - Moderation actions
7. `keystores` - Key-value storage
8. `flagged_commenters` - Comment flagging
9. `mod_notes` - Moderator notes

#### Missing Columns in Existing Tables:

**users table:**
- `about` (bio/description)
- `avatar_file_name`, `avatar_content_type`, etc.
- `pushover_*` notification fields
- `mailing_list_*` fields
- `show_*` preference fields
- `karma` calculation fields

**stories table:**
- `is_expired` boolean
- `unavailable_at` datetime
- `merged_story_id` for duplicate handling
- Various social media ID fields

**comments table:**
- `hat_id` for badge display
- `thread_id` for threading
- `confidence` scoring
- Reply tracking fields

### Phased Implementation Plan

## PHASE 1: Core Voting & Interactions (Week 1-2)
**Priority: Critical - Core functionality missing**

### Database Changes:
- Fix vote table structure to match Lobsters exactly
- Add missing story/comment vote columns
- Update user karma calculation

### Backend Implementation:
- StoryController voting endpoints
- CommentController voting endpoints  
- Karma calculation service
- Vote validation logic

### Frontend Implementation:
- Vote buttons on stories (▲ ▼)
- Vote buttons on comments
- Score display
- Karma display on user profiles

### Routes to Add:
```
POST /stories/{id}/vote
POST /comments/{id}/vote
```

## PHASE 2: Comment System Completion (Week 2-3)
**Priority: Critical - Core functionality**

### Database Changes:
- Add comment threading fields
- Add comment confidence scoring
- Update comment display logic

### Backend Implementation:
- Comment creation controller
- Comment reply system
- Comment threading logic
- Comment markdown processing

### Frontend Implementation:
- Comment creation forms
- Reply interface
- Comment threading display
- Comment editing

### Routes to Add:
```
POST /comments
POST /comments/{id}/reply
PUT /comments/{id}
DELETE /comments/{id}
```

## PHASE 3: User Profile & Social Features (Week 3-4)
**Priority: High - User engagement**

### Database Changes:
- Add user about fields
- Add saved stories table
- Add read ribbons table
- User preference fields

### Backend Implementation:
- User profile management
- Story saving functionality
- Read tracking
- User activity feeds

### Frontend Implementation:
- Complete user profile pages
- User settings page
- Story saving interface
- Activity history

### Routes to Add:
```
GET /settings
POST /settings
POST /stories/{id}/save
DELETE /stories/{id}/save
GET /u/{username}/saved
```

## PHASE 4: Messaging System (Week 4-5)
**Priority: High - Core social feature**

### Database Changes:
- Add messages table
- Add inbox/outbox logic
- Message threading

### Backend Implementation:
- Message controller
- Inbox controller  
- Message validation
- User blocking

### Frontend Implementation:
- Inbox interface
- Message composition
- Message threads
- Notification system

### Routes to Add:
```
GET /messages
GET /messages/sent
POST /messages
GET /messages/{id}
PUT /messages/{id}
```

## PHASE 5: Moderation System (Week 5-6)
**Priority: Medium - Platform safety**

### Database Changes:
- Add moderation tables
- Add flagging system
- Ban system tables

### Backend Implementation:
- Moderation controllers
- Flagging logic
- Ban system
- Moderation logs

### Frontend Implementation:
- Moderation interface
- Flag buttons
- Admin dashboard
- Moderation logs view

### Routes to Add:
```
GET /moderation
POST /stories/{id}/flag
POST /comments/{id}/flag
POST /users/{id}/ban
```

## PHASE 6: Hat System & Avatars (Week 6-7)
**Priority: Low - Enhancement features**

### Database Changes:
- Add hats table
- Add hat_requests table
- Avatar storage

### Backend Implementation:
- Hat system logic
- Avatar upload
- Hat request management

### Frontend Implementation:
- Hat display
- Avatar upload
- Hat request interface

## PHASE 7: Advanced Features (Week 7-8)
**Priority: Low - Polish features**

### Features:
- Story merging for duplicates
- Advanced search filters
- RSS feeds
- Email notifications
- Story expiration
- Domain management

---

## Implementation Order Priority

### IMMEDIATE (This Week):
1. **Story Voting** - Most critical missing feature
2. **Comment Creation** - Essential for discussion
3. **Basic Karma** - User engagement

### NEXT (Weeks 2-3):
4. **Comment Voting & Threading**
5. **User Profile Completion**
6. **Story Saving**

### LATER (Weeks 4+):
7. **Private Messaging**
8. **Moderation System**
9. **Hat System**
10. **Advanced Features**

---

## Success Metrics
- [ ] Users can vote on stories and comments
- [ ] Users can create and reply to comments
- [ ] User karma system works
- [ ] Private messaging functional
- [ ] Basic moderation tools available
- [ ] All core Lobsters tables implemented
- [ ] Feature parity with Lobsters core functionality

This plan ensures we build the most critical user-facing features first while maintaining the foundation for advanced features later.