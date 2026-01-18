<?php

@require_once __DIR__ . '/app/config/config.php';

session_start();

$user_id = $_SESSION['user_id'];

if(!isset($user_id)){
   header('location:login.php');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>About</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/style.css">

</head>
<body>
   
<?php require_once __DIR__ . '/app/includes/header.php'; ?>

<section class="about">

   <div class="row">

      <div class="box">
         <img src="images/about-img-1.png" alt="">
         <h3>Why choose us?</h3>
         <p>We are the current leaders in distribution of healthy products.</p>
         <a href="contact.php" class="btn">Contact us</a>
      </div>

      <div class="box">
         <img src="images/about-img-2.png" alt="">
         <h3>What do we provide?</h3>
         <p>We provide a big range of healthy products, ready for your table.</p>
         <a href="shop.php" class="btn">Our shop</a>
      </div>

   </div>

</section>

<section class="reviews">

   <h1 class="title">Clients reivews</h1>

   <div class="box-container">

      <div class="box">
         <img src="images/gloria.jpeg" alt="">
         <p>Best in the market!</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Gloria Doda</h3>
      </div>

      <div class="box">
         <img src="images/kristina.jpeg" alt="">
         <p>Everything arrived very fresh!</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Kristina Metoshi</h3>
      </div>

      <div class="box">
         <img src="images/alisia.jpeg" alt="">
         <p>It was perfect and fresh!</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Alisia Myzyri</h3>
      </div>

      <div class="box">
         <img src="images/agri.jpeg" alt="">
         <p>It makes ordering so easy!</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Agri Korra</h3>
      </div>

      <div class="box">
         <img src="images/rea.jpeg" alt="">
         <p>Ecerything was tasty and fresh.</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Rea Bogdani</h3>
      </div>

      <div class="box">
         <img src="images/pic-6.png" alt="">
         <p>Very good service!</p>
         <div class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star-half-alt"></i>
         </div>
         <h3>Anonymous user</h3>
      </div>

   </div>

</section>









<?php require_once __DIR__ . '/app/includes/footer.php'; ?>

<script src="js/script.js"></script>

</body>
</html>