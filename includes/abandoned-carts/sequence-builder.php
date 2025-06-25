<?php
/**
 * Render the Abandoned Cart Email Sequence Builder
 */
function render_abandoned_sequence_builder_page() {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['hubspot_email_sequence_nonce']) &&
        wp_verify_nonce($_POST['hubspot_email_sequence_nonce'], 'save_email_sequence')) {
        
        $raw = stripslashes($_POST['email_sequence_data']);
        $sequence = json_decode($raw, true);
        
        if (is_array($sequence)) {
            update_option('hubspot_abandoned_sequence', $sequence);
            echo '<div class="notice notice-success"><p>Email sequence saved successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Invalid sequence data.</p></div>';
        }
    }

    $sequence = get_option('hubspot_abandoned_sequence', []);
    $templates = get_hubspot_email_templates();
    ?>
    <div class="wrap">
        <h1>Email Sequence Builder</h1>
        <form method="post" id="email-sequence-form">
            <?php wp_nonce_field('save_email_sequence', 'hubspot_email_sequence_nonce'); ?>

            <ul id="email-sequence-list" class="sequence-list">
                <?php foreach ($sequence as $step): ?>
                    <li class="sequence-step">
                        <input type="text" class="step-delay" placeholder="Delay (e.g. 30 minutes)" value="<?= esc_attr($step['delay'] ?? '') ?>" />
                        <select class="step-template">
                            <?php foreach ($templates as $id => $tpl): ?>
                                <option value="<?= esc_attr($id) ?>" <?= selected($id, $step['template'] ?? '') ?>>
                                    <?= esc_html($tpl['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" class="step-discount" placeholder="Discount Code (optional)" value="<?= esc_attr($step['discount'] ?? '') ?>" />
                        <button type="button" class="remove-step button">Remove</button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <button type="button" id="add-sequence-step" class="button">+ Add Step</button>
            <input type="hidden" name="email_sequence_data" id="email_sequence_data" />
            <p><input type="submit" class="button button-primary" value="Save Sequence" /></p>
        </form>
    </div>

    <style>
        .sequence-list { list-style: none; padding: 0; }
        .sequence-step { background: #fff; padding: 10px; margin-bottom: 8px; border: 1px solid #ccc; display: flex; gap: 8px; align-items: center; }
        .sequence-step input, .sequence-step select { flex: 1; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        const list = document.getElementById('email-sequence-list');
        new Sortable(list, { animation: 150 });

        document.getElementById('add-sequence-step').addEventListener('click', () => {
            const templates = <?= json_encode($templates) ?>;
            const li = document.createElement('li');
            li.className = 'sequence-step';

            const templateSelect = Object.entries(templates).map(([id, tpl]) =>
                `<option value="${id}">${tpl.label}</option>`).join('');

            li.innerHTML = `
                <input type="text" class="step-delay" placeholder="Delay (e.g. 30 minutes)" />
                <select class="step-template">${templateSelect}</select>
                <input type="text" class="step-discount" placeholder="Discount Code (optional)" />
                <button type="button" class="remove-step button">Remove</button>
            `;
            list.appendChild(li);
        });

        list.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-step')) {
                e.target.parentElement.remove();
            }
        });

        document.getElementById('email-sequence-form').addEventListener('submit', function () {
            const steps = [...document.querySelectorAll('.sequence-step')].map(el => ({
                delay: el.querySelector('.step-delay').value,
                template: el.querySelector('.step-template').value,
                discount: el.querySelector('.step-discount').value,
            }));
            document.getElementById('email_sequence_data').value = JSON.stringify(steps);
        });
    </script>
<?php
}
