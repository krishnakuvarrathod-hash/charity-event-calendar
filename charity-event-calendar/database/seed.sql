-- ========================================================
-- Charity Event Calendar - Seed Data Script
-- Purpose: Realistic sample data for academic demonstration
-- Idempotent: Can be run multiple times safely
-- Default demo password for all seeded accounts: Password@123
-- ========================================================

USE charity_calendar;

-- --------------------------------------------------------
-- 1. Seed Users
-- Passwords hashed with bcrypt: 'Password@123'
-- Hash: $2y$10$ihpvRU4353dVxEUs73fDcekzFLggbixDKw7TGf8p/nQymXWichSKS
-- --------------------------------------------------------
INSERT INTO users (id, full_name, email, password_hash, role) VALUES
(1, 'System Administrator', 'admin@charitycalendar.org', '$2y$10$ihpvRU4353dVxEUs73fDcekzFLggbixDKw7TGf8p/nQymXWichSKS', 'admin'),
(2, 'Sarah Jenkins', 'sarah@hopefoundation.org', '$2y$10$ihpvRU4353dVxEUs73fDcekzFLggbixDKw7TGf8p/nQymXWichSKS', 'organizer'),
(3, 'Marcus Vance', 'marcus@foodforall.org', '$2y$10$ihpvRU4353dVxEUs73fDcekzFLggbixDKw7TGf8p/nQymXWichSKS', 'organizer'),
(4, 'Priya Patel', 'priya@cleanrivers.org', '$2y$10$ihpvRU4353dVxEUs73fDcekzFLggbixDKw7TGf8p/nQymXWichSKS', 'organizer'),
(5, 'David Chen', 'david@seniorcare.org', '$2y$10$ihpvRU4353dVxEUs73fDcekzFLggbixDKw7TGf8p/nQymXWichSKS', 'organizer'),
(6, 'John Doe', 'john.doe@example.com', '$2y$10$ihpvRU4353dVxEUs73fDcekzFLggbixDKw7TGf8p/nQymXWichSKS', 'visitor'),
(7, 'Emily Watson', 'emily.watson@example.com', '$2y$10$ihpvRU4353dVxEUs73fDcekzFLggbixDKw7TGf8p/nQymXWichSKS', 'visitor')
ON DUPLICATE KEY UPDATE 
    full_name = VALUES(full_name),
    role = VALUES(role);

-- --------------------------------------------------------
-- 2. Seed Charities (Linked to Organizers)
-- --------------------------------------------------------
INSERT INTO charities (id, user_id, charity_name, description, email, phone, website, address, city, state) VALUES
(1, 2, 'Hope Children Foundation', 'Dedicated to providing educational resources, school kits, and nutrition for underprivileged youth.', 'contact@hopefoundation.org', '+1 (555) 234-5678', 'https://hopefoundation.example.org', '140 Grand Central Pkwy', 'New York', 'NY'),
(2, 3, 'Food For All Network', 'Fighting local hunger through daily soup kitchens, surplus food redistribution, and pantry supply.', 'support@foodforall.org', '+1 (555) 345-6789', 'https://foodforall.example.org', '820 Congress Avenue', 'Austin', 'TX'),
(3, 4, 'Clean Rivers & Green Earth', 'Mobilizing volunteers for river cleanup, urban forestry, and renewable ecology education.', 'hello@cleanrivers.org', '+1 (555) 456-7890', 'https://cleanrivers.example.org', '200 Elliott Avenue West', 'Seattle', 'WA'),
(4, 5, 'Senior Care & Wellness Alliance', 'Supporting elderly citizens with free health screenings, mobility assistance, and social connectivity.', 'care@seniorcare.org', '+1 (555) 567-8901', 'https://seniorcare.example.org', '330 N Michigan Ave', 'Chicago', 'IL')
ON DUPLICATE KEY UPDATE 
    charity_name = VALUES(charity_name),
    description = VALUES(description),
    city = VALUES(city);

-- --------------------------------------------------------
-- 3. Seed Venues
-- --------------------------------------------------------
INSERT INTO venues (id, venue_name, address, city, state, postal_code, is_online) VALUES
(1, 'Central Park Great Lawn', 'Mid-Park from 79th to 85th St', 'New York', 'NY', '10024', 0),
(2, 'Austin Community & Cultural Hall', '1100 E 5th St', 'Austin', 'TX', '78702', 0),
(3, 'Elliott Bay Park Promenade', '3101 Alaskan Way', 'Seattle', 'WA', '98121', 0),
(4, 'Chicago Millennium Pavilion', '201 E Randolph St', 'Chicago', 'IL', '60602', 0),
(5, 'Global Virtual Amphitheater', 'https://stream.charitycalendar.org/live', 'Online', 'Virtual', '00000', 1)
ON DUPLICATE KEY UPDATE 
    venue_name = VALUES(venue_name),
    city = VALUES(city);

-- --------------------------------------------------------
-- 4. Seed Events
-- Categories: Fundraising, Food Donation, Volunteering, Health Drive, Community Activity
-- --------------------------------------------------------
INSERT INTO events (id, charity_id, venue_id, title, description, category, start_datetime, end_datetime, registration_deadline, capacity, image_url, status, rejection_reason) VALUES
(1, 1, 1, '5K Charity Run for Youth Education', 'Join hundreds of runners in Central Park to raise funds for underprivileged children textbooks and scholarship grants. Family-friendly route with hydration stations.', 'Fundraising', DATE_ADD(NOW(), INTERVAL 14 DAY), DATE_ADD(NOW(), INTERVAL 14 DAY) + INTERVAL 4 HOUR, DATE_ADD(NOW(), INTERVAL 12 DAY), 200, 'https://images.unsplash.com/photo-1552674605-db6ffd4facb5?auto=format&fit=crop&w=800&q=80', 'approved', NULL),

(2, 2, 2, 'Winter Food & Blanket Drive', 'Help assemble 5,000 grocery packages and distribute warm winter blankets to families experiencing homelessness across Travis County.', 'Food Donation', DATE_ADD(NOW(), INTERVAL 20 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY) + INTERVAL 6 HOUR, DATE_ADD(NOW(), INTERVAL 18 DAY), 100, 'https://images.unsplash.com/photo-1593113598332-cd288d649433?auto=format&fit=crop&w=800&q=80', 'approved', NULL),

(3, 3, 3, 'Seattle Waterfront & Bay Clean-Up Day', 'Lend a hand picking up shoreline debris, planting native marine flora, and learning about watershed protection with marine biologists.', 'Volunteering', DATE_ADD(NOW(), INTERVAL 25 DAY), DATE_ADD(NOW(), INTERVAL 25 DAY) + INTERVAL 5 HOUR, DATE_ADD(NOW(), INTERVAL 23 DAY), 75, 'https://images.unsplash.com/photo-1618477461853-cf6ed80faba5?auto=format&fit=crop&w=800&q=80', 'approved', NULL),

(4, 4, 4, 'Community Health & Blood Pressure Screening', 'Free health examinations, diabetes checkups, and nutritional advice for seniors and families in Cook County. Walk-ins and pre-registrations welcome.', 'Health Drive', DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY) + INTERVAL 7 HOUR, DATE_ADD(NOW(), INTERVAL 28 DAY), 150, 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80', 'approved', NULL),

(5, 1, 5, 'Virtual Benefit Concert & Storytelling', 'An inspiring evening of live acoustic music and heartwarming stories from scholarship recipients broadcast live across the country.', 'Fundraising', DATE_ADD(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 10 DAY) + INTERVAL 3 HOUR, DATE_ADD(NOW(), INTERVAL 9 DAY), 500, 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=800&q=80', 'approved', NULL),

(6, 2, 2, 'Holiday Thanksgiving Meal Prep Assistance', 'Volunteer in our commercial kitchen prepping fresh turkeys, roasted vegetables, and pumpkin pies for Thanksgiving dinner deliveries.', 'Food Donation', DATE_ADD(NOW(), INTERVAL 45 DAY), DATE_ADD(NOW(), INTERVAL 45 DAY) + INTERVAL 8 HOUR, DATE_ADD(NOW(), INTERVAL 40 DAY), 60, 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=800&q=80', 'approved', NULL),

-- Demonstration: Pending Event (Awaiting Admin Review)
(7, 3, 3, 'Urban Seed Bombing & Pollinator Garden Workshop', 'A community workshop where participants construct clay seed balls of native wildflowers and plant pollinator-friendly gardens along bicycle trails.', 'Volunteering', DATE_ADD(NOW(), INTERVAL 15 DAY), DATE_ADD(NOW(), INTERVAL 15 DAY) + INTERVAL 4 HOUR, DATE_ADD(NOW(), INTERVAL 13 DAY), 40, 'https://images.unsplash.com/photo-1466692476868-aef1dfb1e735?auto=format&fit=crop&w=800&q=80', 'pending', NULL),

-- Demonstration: Rejected Event (With documented feedback for the organizer)
(8, 4, 4, 'Unlicensed Fireworks Show & Fundraiser', 'A fireworks celebration to attract neighborhood donors to our senior mobility center.', 'Fundraising', DATE_ADD(NOW(), INTERVAL 35 DAY), DATE_ADD(NOW(), INTERVAL 35 DAY) + INTERVAL 3 HOUR, DATE_ADD(NOW(), INTERVAL 30 DAY), 300, 'https://images.unsplash.com/photo-1498931299472-f7a63a5a1cfa?auto=format&fit=crop&w=800&q=80', 'rejected', 'City fire safety permits and municipal pyrotechnic insurance certifications were not provided with the application.'),

-- Demonstration: Cancelled Event
(9, 1, 1, 'Outdoor Charity Cycling Derby (Postponed)', 'Charity bike race across Manhattan postponed due to road resurfacing work scheduled on park perimeter.', 'Community Activity', DATE_ADD(NOW(), INTERVAL 60 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY) + INTERVAL 5 HOUR, DATE_ADD(NOW(), INTERVAL 55 DAY), 80, 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=800&q=80', 'cancelled', NULL)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    category = VALUES(category),
    status = VALUES(status),
    rejection_reason = VALUES(rejection_reason);

-- --------------------------------------------------------
-- 5. Seed Registrations
-- John Doe (id=6) and Emily Watson (id=7) registered for events
-- --------------------------------------------------------
INSERT INTO registrations (id, event_id, user_id, registration_date, status) VALUES
(1, 1, 6, NOW() - INTERVAL 2 DAY, 'confirmed'),
(2, 2, 6, NOW() - INTERVAL 1 DAY, 'confirmed'),
(3, 1, 7, NOW() - INTERVAL 3 DAY, 'confirmed'),
(4, 3, 7, NOW() - INTERVAL 1 DAY, 'confirmed')
ON DUPLICATE KEY UPDATE 
    status = VALUES(status);
