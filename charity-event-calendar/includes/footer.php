<?php
/**
 * Global Footer Component
 */
?>
<footer class="mt-auto">
    <div class="container py-4">
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <h5 class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-heart-pulse-fill text-danger"></i>
                    <span>Charity Event Calendar</span>
                </h5>
                <p class="text-secondary small mb-3">
                    A centralized, scalable online platform uniting donors, volunteers, and local nonprofits.
                    Engineered for high availability on AWS with Auto Scaling, Application Load Balancers, and Amazon RDS.
                </p>
                <div class="d-flex gap-2">
                    <span class="badge bg-secondary-subtle text-light border border-secondary">AWS Cloud Native</span>
                    <span class="badge bg-secondary-subtle text-light border border-secondary">PHP 8 & MySQL</span>
                </div>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <h6 class="text-white fw-semibold mb-3">Platform</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="/index.php">Home</a></li>
                    <li class="mb-2"><a href="/events.php">Explore Events</a></li>
                    <li class="mb-2"><a href="/charities.php">Nonprofit Directory</a></li>
                    <li class="mb-2"><a href="/health.php" target="_blank">Health Status <i class="bi bi-box-arrow-up-right ms-1"></i></a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <h6 class="text-white fw-semibold mb-3">Get Involved</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="/register.php">Create Account</a></li>
                    <li class="mb-2"><a href="/login.php">Organizer Portal</a></li>
                    <li class="mb-2"><a href="/login.php">Admin Sign In</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-12">
                <h6 class="text-white fw-semibold mb-3">Academic Project Details</h6>
                <p class="text-secondary small mb-2">
                    Developed for College Cloud Computing Demonstration. Demonstrating Multi-AZ High Availability, AWS Secrets Manager credential caching, and transaction-safe event capacity enforcement.
                </p>
                <p class="text-secondary small mb-0">
                    <i class="bi bi-shield-check text-success me-1"></i> Verified with AWS Well-Architected Reliability Pillar.
                </p>
            </div>
        </div>

        <div class="border-top border-secondary pt-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <span class="small text-secondary">&copy; <?= date('Y') ?> Charity Event Calendar. Academic Demonstration Project.</span>
            <span class="small text-secondary">
                <span class="text-success"><i class="bi bi-circle-fill" style="font-size: 8px;"></i> System Status: Operational</span>
            </span>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<!-- Application Custom JS -->
<script src="/assets/js/main.js"></script>
</body>
</html>
