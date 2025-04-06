<div class="voice-player">
    <button class="play-btn" onclick="voicePlayer.playSummary(<?= $email['id'] ?>, '<?= $userLanguage ?>')">
        🎧 Play Summary 
        <span class="lang-badge"><?= strtoupper($userLanguage) ?></span>
    </button>
    <div class="loading-indicator" style="display:none;">
        Generating audio...
    </div>
</div>