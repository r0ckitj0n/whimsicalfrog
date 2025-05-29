<?php
// Landing page section
?>
<section id="landingPage" class="text-center rounded-lg min-h-[calc(100vh-150px)] flex flex-col justify-center items-center relative">
    <!-- The div that might have caused a bar has been removed -->

    <!-- Clickable Door Area -->
    <a href="/?page=main_room" title="Enter the Main Room" style="position: absolute; top: calc(50% + 450px); left: 50%; width: 300px; height: 300px; transform: translate(-50%, -50%); background-color: transparent; /* Visualizing click area removed */ cursor: pointer;">
        <span class="sr-only">Enter the Main Room</span> <!-- Accessibility text -->
    </a>
</section>
<style>
    #landingPage {
        background-image: url('images/webp/home_background.webp'); 
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        position: relative; /* Needed for absolute positioning of the child link */
    }
    .cottage-bg { 
        /* This class is no longer used on the section, can be removed or kept if used elsewhere */
    }
</style> 