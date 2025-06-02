<?php
// Landing page section
?>
<section id="landingPage" class="text-center rounded-lg min-h-[calc(100vh-150px)] flex flex-col justify-center items-center relative">
    
    <!-- Centered Welcome Sign -->
    <a href="/?page=main_room" title="Enter the Main Room" class="block" style="margin-top: 35vh;">
        <img src="images/sign_welcome.webp" alt="Welcome - Click to Enter" class="mx-auto" style="max-height: 150px; width: auto;">
    </a>

</section>
<style>
    #landingPage {
        /* Background image is handled by the body element in index.php */
        position: relative; /* Still useful for potential future absolute positioning within */
    }
    .cottage-bg { 
        /* This class is no longer used on the section, can be removed or kept if used elsewhere */
    }
</style>
