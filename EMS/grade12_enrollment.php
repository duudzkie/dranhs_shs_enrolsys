<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade 12 Pre-Enrollment | DRANHS SmartEnrol</title>
    <link rel="stylesheet" href="grade12_enrollment.css">
</head>
<body>
    <main class="form-page">
        <section class="form-card">
            <p class="form-kicker">DRANHS SmartEnrol</p>
            <h1>Grade 12 Registration Form</h1>
            <p class="form-note">Fill out your registration details. Fields marked with * are required.</p>

            <form action="#" method="post" class="enroll-form" novalidate>
                <div class="form-grid">
                    <div class="field-group">
                        <label for="lrn">LRN (12 digits) *</label>
                        <input
                            type="text"
                            id="lrn"
                            name="lrn"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="12"
                            pattern="[0-9]{12}"
                            placeholder="e.g. 123456789012"
                            required
                        >
                        <small id="lrnHelp">Numbers only. Exactly 12 digits.</small>
                    </div>

                    <div class="field-group">
                        <label for="birthday">Birthday *</label>
                        <input type="date" id="birthday" name="birthday" required>
                    </div>

                    <div class="field-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" placeholder="Juan" required>
                    </div>

                    <div class="field-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" placeholder="Santos">
                    </div>

                    <div class="field-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" placeholder="Dela Cruz" required>
                    </div>

                    <div class="field-group">
                        <label for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="suffix" placeholder="Jr., III">
                    </div>

                    <div class="field-group">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="prefer_not">Prefer not to say</option>
                        </select>
                    </div>

                    <div class="field-group">
                        <label for="strand">Grade 12 Strand *</label>
                        <select id="strand" name="strand" required>
                            <option value="">Select strand</option>
                            <option value="stem">STEM</option>
                            <option value="abm">ABM</option>
                            <option value="humss">HUMSS</option>
                            <option value="gas">GAS</option>
                            <option value="tvl">TVL</option>
                        </select>
                    </div>

                    <div class="field-group">
                        <label for="height_cm">Height (cm)</label>
                        <input type="number" id="height_cm" name="height_cm" min="50" max="250" step="0.1" placeholder="e.g. 165.5">
                    </div>

                    <div class="field-group">
                        <label for="weight_kg">Weight (kg)</label>
                        <input type="number" id="weight_kg" name="weight_kg" min="20" max="300" step="0.1" placeholder="e.g. 55.0">
                    </div>

                    <div class="field-group">
                        <label for="religion">Religion</label>
                        <input type="text" id="religion" name="religion" placeholder="e.g. Roman Catholic">
                    </div>

                    <div class="field-group">
                        <label for="four_ps">4Ps Beneficiary *</label>
                        <select id="four_ps" name="four_ps" required>
                            <option value="">Select option</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>

                    <div class="field-group field-full">
                        <label for="place_of_birth">Place of Birth *</label>
                        <input type="text" id="place_of_birth" name="place_of_birth" placeholder="City / Municipality, Province" required>
                    </div>

                    <div class="field-group field-full">
                        <label for="address">Complete Address *</label>
                        <textarea id="address" name="address" rows="3" placeholder="House No., Street, Barangay, City/Municipality, Province" required></textarea>
                    </div>

                    <div class="field-group">
                        <label for="contact_number">Contact Number *</label>
                        <input type="text" id="contact_number" name="contact_number" inputmode="numeric" maxlength="11" placeholder="09XXXXXXXXX" required>
                    </div>

                    <div class="field-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="name@example.com">
                    </div>

                    <div class="field-group">
                        <label for="guardian_name">Parent/Guardian Name *</label>
                        <input type="text" id="guardian_name" name="guardian_name" placeholder="Full name" required>
                    </div>

                    <div class="field-group">
                        <label for="guardian_contact">Parent/Guardian Contact *</label>
                        <input type="text" id="guardian_contact" name="guardian_contact" inputmode="numeric" maxlength="11" placeholder="09XXXXXXXXX" required>
                    </div>

                    <div class="field-group field-full">
                        <label for="previous_school">Previous School *</label>
                        <input type="text" id="previous_school" name="previous_school" placeholder="School name" required>
                    </div>
                </div>

                <div class="form-actions">
                    <a class="btn btn-back" href="index.php">Back</a>
                    <button class="btn btn-next" type="submit">Submit Registration</button>
                </div>
            </form>
        </section>
    </main>

    <script>
        const lrnInput = document.getElementById("lrn");
        const contactInput = document.getElementById("contact_number");
        const guardianContactInput = document.getElementById("guardian_contact");
        const enrollForm = document.querySelector(".enroll-form");

        function digitsOnly(input, limit) {
            input.value = input.value.replace(/\D/g, "").slice(0, limit);
        }

        lrnInput.addEventListener("input", function () {
            digitsOnly(this, 12);
        });

        contactInput.addEventListener("input", function () {
            digitsOnly(this, 11);
        });

        guardianContactInput.addEventListener("input", function () {
            digitsOnly(this, 11);
        });

        enrollForm.addEventListener("submit", function (event) {
            if (lrnInput.value.length !== 12) {
                event.preventDefault();
                alert("LRN must be exactly 12 digits.");
                lrnInput.focus();
            }
        });
    </script>
</body>
</html>
