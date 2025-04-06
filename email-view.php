<div class="email-body">
    <?php echo $email_body; ?>
</div>

<!-- 🎧 AI Voice Summary Control -->
<div class="voice-player-container" aria-label="Email voice summary player">
    <div class="language-selector">
        <label for="voice-lang">🔤 Select Language:</label>
        <select id="voice-lang" class="lang-select">
            <option value="en">English</option>
            <option value="bn">Bengali</option>
            <option value="hi">Hindi</option>
        </select>
    </div>
    
    <button 
        class="play-button" 
        id="play-voice-summary" 
        data-email-id="<?php echo $email_id; ?>"
        aria-label="Play email summary"
    >
        🎧 Listen to Summary
    </button>
</div>

<!-- Load styles and scripts -->
<link rel="stylesheet" href="/atom-mail-ai/assets/css/voice-player.css">
<script src="/atom-mail-ai/assets/js/voice-player.js"></script>