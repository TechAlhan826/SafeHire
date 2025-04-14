# SafeHire API Documentation

## Overview

SafeHire's API is structured into eight main modules to handle all aspects of the freelancer-client platform:

1. **Authentication** - User registration, login, and account management
2. **Projects** - Project creation, management, and retrieval
3. **Bids** - Proposal submission, acceptance, and management
4. **Chat** - Messaging between users
5. **Payments** - Secure payment processing and milestone handling
6. **AI Matching** - Intelligent freelancer-project matching
7. **Video Calls** - Real-time video communication
8. **Location** - Proximity-based features

## Base URL

All API endpoints are relative to the base URL:

```
http://0.0.0.0:5000/api
```

## Authentication

All authenticated endpoints require a valid JWT token in the Authorization header:

```
Authorization: Bearer {token}
```

### Endpoints

#### Register

```
POST /auth/register
```

Request Body:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secure_password",
  "password_confirmation": "secure_password",
  "role": "freelancer", // or "client"
  "phone": "+1234567890" // optional
}
```

Response:
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "freelancer",
      "created_at": "2025-04-05T10:00:00.000Z"
    }
  }
}
```

#### Login

```
POST /auth/login
```

Request Body:
```json
{
  "email": "john@example.com",
  "password": "secure_password"
}
```

Response:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "freelancer",
      "created_at": "2025-04-05T10:00:00.000Z"
    }
  }
}
```

#### Logout

```
POST /auth/logout
```

Response:
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

#### Get Profile

```
GET /auth/profile
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "freelancer",
    "phone": "+1234567890",
    "bio": "Experienced web developer...",
    "skills": ["PHP", "JavaScript", "React"],
    "created_at": "2025-04-05T10:00:00.000Z"
  }
}
```

## Projects

### Endpoints

#### Get All Projects

```
GET /projects
```

Query Parameters:
- `page` - Page number for pagination (default: 1)
- `limit` - Results per page (default: 10)
- `status` - Filter by status (open, in_progress, completed)
- `category` - Filter by category
- `budget_min` - Minimum budget
- `budget_max` - Maximum budget

Response:
```json
{
  "success": true,
  "data": {
    "projects": [
      {
        "id": 1,
        "title": "Website Development",
        "description": "Build a responsive website...",
        "budget": 1000,
        "deadline": "2025-05-15T00:00:00.000Z",
        "category": "Web Development",
        "status": "open",
        "skills_required": ["PHP", "JavaScript", "HTML/CSS"],
        "client": {
          "id": 2,
          "name": "Jane Smith"
        },
        "created_at": "2025-04-05T10:00:00.000Z"
      },
      // More projects...
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_results": 48,
      "limit": 10
    }
  }
}
```

#### Get Project By ID

```
GET /projects/:id
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Website Development",
    "description": "Build a responsive website...",
    "budget": 1000,
    "deadline": "2025-05-15T00:00:00.000Z",
    "category": "Web Development",
    "status": "open",
    "skills_required": ["PHP", "JavaScript", "HTML/CSS"],
    "client": {
      "id": 2,
      "name": "Jane Smith",
      "email": "jane@example.com",
      "rating": 4.8
    },
    "created_at": "2025-04-05T10:00:00.000Z",
    "updated_at": "2025-04-05T10:00:00.000Z",
    "milestones": [
      {
        "id": 1,
        "name": "Design Phase",
        "due_date": "2025-04-25T00:00:00.000Z",
        "amount": 250,
        "status": "pending"
      }
    ]
  }
}
```

#### Create Project

```
POST /projects
```

Request Body:
```json
{
  "title": "Website Development",
  "description": "Build a responsive website...",
  "budget": 1000,
  "deadline": "2025-05-15T00:00:00.000Z",
  "category": "Web Development",
  "skills_required": ["PHP", "JavaScript", "HTML/CSS"],
  "milestones": [
    {
      "name": "Design Phase",
      "due_date": "2025-04-25T00:00:00.000Z",
      "amount": 250
    },
    {
      "name": "Development Phase",
      "due_date": "2025-05-10T00:00:00.000Z",
      "amount": 500
    },
    {
      "name": "Testing & Deployment",
      "due_date": "2025-05-15T00:00:00.000Z",
      "amount": 250
    }
  ]
}
```

Response:
```json
{
  "success": true,
  "message": "Project created successfully",
  "data": {
    "id": 1,
    "title": "Website Development",
    "description": "Build a responsive website...",
    "budget": 1000,
    "deadline": "2025-05-15T00:00:00.000Z",
    "category": "Web Development",
    "status": "open",
    "skills_required": ["PHP", "JavaScript", "HTML/CSS"],
    "created_at": "2025-04-05T10:00:00.000Z"
  }
}
```

## Bids

### Endpoints

#### Submit Bid

```
POST /bids/submit
```

Request Body:
```json
{
  "project_id": 1,
  "amount": 950,
  "delivery_time": 30, // days
  "description": "I can build this website efficiently...",
  "milestones": [
    {
      "name": "Design Phase",
      "due_date": "2025-04-25T00:00:00.000Z",
      "amount": 200
    },
    {
      "name": "Development Phase",
      "due_date": "2025-05-10T00:00:00.000Z",
      "amount": 500
    },
    {
      "name": "Testing & Deployment",
      "due_date": "2025-05-15T00:00:00.000Z",
      "amount": 250
    }
  ]
}
```

Response:
```json
{
  "success": true,
  "message": "Bid submitted successfully",
  "data": {
    "id": 1,
    "project_id": 1,
    "freelancer_id": 1,
    "amount": 950,
    "status": "pending",
    "created_at": "2025-04-05T10:00:00.000Z"
  }
}
```

#### Get Bids by Project

```
GET /bids/project/:id
```

Query Parameters:
- `status` - Filter by status (pending, accepted, rejected)

Response:
```json
{
  "success": true,
  "data": {
    "bids": [
      {
        "id": 1,
        "amount": 950,
        "delivery_time": 30,
        "description": "I can build this website efficiently...",
        "status": "pending",
        "freelancer": {
          "id": 1,
          "name": "John Doe",
          "rating": 4.7,
          "completed_projects": 12
        },
        "created_at": "2025-04-05T10:00:00.000Z"
      },
      // More bids...
    ]
  }
}
```

#### Accept Bid

```
POST /bids/:id/accept
```

Response:
```json
{
  "success": true,
  "message": "Bid accepted successfully",
  "data": {
    "id": 1,
    "status": "accepted",
    "project_id": 1,
    "project_status": "in_progress"
  }
}
```

## Chat

### Endpoints

#### Get Chats

```
GET /chats
```

Response:
```json
{
  "success": true,
  "data": {
    "chats": [
      {
        "id": 1,
        "user": {
          "id": 2,
          "name": "Jane Smith",
          "avatar": "https://example.com/avatar.jpg"
        },
        "last_message": {
          "content": "How is the project going?",
          "timestamp": "2025-04-05T10:00:00.000Z",
          "is_read": false
        },
        "unread_count": 2
      },
      // More chats...
    ]
  }
}
```

#### Get Messages

```
GET /chats/:id/messages
```

Query Parameters:
- `page` - Page number for pagination (default: 1)
- `limit` - Results per page (default: 20)

Response:
```json
{
  "success": true,
  "data": {
    "chat_id": 1,
    "with_user": {
      "id": 2,
      "name": "Jane Smith",
      "avatar": "https://example.com/avatar.jpg"
    },
    "messages": [
      {
        "id": 1,
        "sender_id": 1,
        "content": "Hello, I'm interested in discussing the project",
        "attachments": [],
        "created_at": "2025-04-05T09:30:00.000Z"
      },
      {
        "id": 2,
        "sender_id": 2,
        "content": "Great! What questions do you have?",
        "attachments": [],
        "created_at": "2025-04-05T09:32:00.000Z"
      },
      // More messages...
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 3,
      "total_results": 45,
      "limit": 20
    }
  }
}
```

#### Send Message

```
POST /chats/:id/messages
```

Request Body:
```json
{
  "message": "I've completed the first milestone",
  "attachments": [] // File uploads handled via multipart/form-data
}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 3,
    "sender_id": 1,
    "content": "I've completed the first milestone",
    "attachments": [],
    "created_at": "2025-04-05T10:05:00.000Z"
  }
}
```

## Payments

### Endpoints

#### Create Payment Intent

```
POST /payments/create-intent
```

Request Body:
```json
{
  "amount": 250,
  "currency": "usd",
  "payment_method_id": "pm_card_visa",
  "description": "Payment for milestone: Design Phase",
  "project_id": 1,
  "milestone_id": 1
}
```

Response:
```json
{
  "success": true,
  "data": {
    "client_secret": "pi_1J4JkEJ7dV29Eyasdf8U3jL_secret_8U3jLasdfas",
    "payment_intent_id": "pi_1J4JkEJ7dV29Eyasdf8U3jL"
  }
}
```

#### Get Transaction History

```
GET /payments/transactions
```

Query Parameters:
- `type` - Transaction type (incoming, outgoing, all)
- `status` - Transaction status (completed, pending, failed)
- `date_from` - Filter from date
- `date_to` - Filter to date

Response:
```json
{
  "success": true,
  "data": {
    "transactions": [
      {
        "id": "txn_1J4JkEJ7dV29Eyasdf8U3jL",
        "amount": 250,
        "currency": "usd",
        "status": "completed",
        "type": "outgoing",
        "description": "Payment for milestone: Design Phase",
        "project": {
          "id": 1,
          "title": "Website Development"
        },
        "created_at": "2025-04-05T10:00:00.000Z"
      },
      // More transactions...
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 2,
      "total_results": 12,
      "limit": 10
    }
  }
}
```

## AI Matching

### Endpoints

#### Get Project Matches

```
GET /ai/matches/:projectId
```

Response:
```json
{
  "success": true,
  "data": {
    "matches": [
      {
        "freelancer": {
          "id": 1,
          "name": "John Doe",
          "skills": ["PHP", "JavaScript", "React"],
          "rating": 4.8,
          "completed_projects": 15
        },
        "match_score": 95,
        "match_reasons": [
          "Skill match: 5/5 required skills",
          "Successfully completed similar projects",
          "High ratings from previous clients"
        ]
      },
      // More matches...
    ]
  }
}
```

#### Get Project Recommendations

```
GET /ai/recommendations
```

Response:
```json
{
  "success": true,
  "data": {
    "recommendations": [
      {
        "id": 1,
        "title": "Website Development",
        "budget": 1000,
        "match_score": 92,
        "skills_matched": ["PHP", "JavaScript"],
        "client": {
          "id": 2,
          "name": "Jane Smith",
          "rating": 4.9
        }
      },
      // More recommendations...
    ]
  }
}
```

## Video Calls

### Endpoints

#### Create Call

```
POST /calls
```

Request Body:
```json
{
  "userId": 2
}
```

Response:
```json
{
  "success": true,
  "data": {
    "call_id": "call_12345",
    "token": "your_agora_token",
    "channel": "safehire_call_12345",
    "with_user": {
      "id": 2,
      "name": "Jane Smith"
    }
  }
}
```

#### Join Call

```
POST /calls/:id/join
```

Response:
```json
{
  "success": true,
  "data": {
    "call_id": "call_12345",
    "token": "your_agora_token",
    "channel": "safehire_call_12345",
    "with_user": {
      "id": 1,
      "name": "John Doe"
    }
  }
}
```

## Location

### Endpoints

#### Update Location

```
POST /location
```

Request Body:
```json
{
  "latitude": 37.7749,
  "longitude": -122.4194
}
```

Response:
```json
{
  "success": true,
  "message": "Location updated successfully"
}
```

#### Get Nearby Freelancers

```
GET /location/nearby
```

Query Parameters:
- `radius` - Search radius in kilometers (default: 10)
- `skills` - Comma-separated list of required skills

Response:
```json
{
  "success": true,
  "data": {
    "freelancers": [
      {
        "id": 1,
        "name": "John Doe",
        "skills": ["PHP", "JavaScript", "React"],
        "rating": 4.8,
        "distance": 2.5, // kilometers
        "location": {
          "latitude": 37.7825,
          "longitude": -122.4078
        }
      },
      // More freelancers...
    ]
  }
}
```

## Error Handling

All API endpoints follow a consistent error format:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid input provided",
    "details": {
      "email": ["Email is required"],
      "password": ["Password must be at least 8 characters"]
    }
  }
}
```

Common error codes:
- `VALIDATION_ERROR` - Input validation failed
- `AUTHENTICATION_ERROR` - Authentication issues (invalid/expired token)
- `AUTHORIZATION_ERROR` - User lacks permission for the action
- `RESOURCE_NOT_FOUND` - Requested resource not found
- `INTERNAL_SERVER_ERROR` - Unexpected server error

## Rate Limiting

API requests are rate-limited to prevent abuse. Limits vary by endpoint but generally allow:
- 60 requests per minute for authenticated users
- 10 requests per minute for unauthenticated users

Rate limit headers in responses:
- `X-RateLimit-Limit` - Requests allowed per window
- `X-RateLimit-Remaining` - Requests remaining in current window
- `X-RateLimit-Reset` - Time when the rate limit resets (Unix timestamp)

When rate limit is exceeded, the API returns a 429 Too Many Requests response.
