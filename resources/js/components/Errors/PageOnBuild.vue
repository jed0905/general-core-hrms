<template>
  <div class="page-on-build">
    <div class="container">
      <div class="content-wrapper">
        <div class="image-container">
          <img 
            :src="pageOnBuild" 
            alt="Coming Soon" 
            class="coming-soon-image"
          />
        </div>
        <div class="text-content">
          <h1 class="title">Page Under Construction</h1>
          <p class="subtitle">We're working hard to bring you something amazing!</p>
          <p class="description">
            This page is currently being built. Please check back soon for updates.
          </p>
          <div class="loading-indicator">
            <div class="spinner"></div>
            <span class="loading-text">Building in progress...</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import pageOnBuild from '../../../images/coming-soon-design.png'
  export default {
    data(){
      return {
        pageOnBuild,
      }
    }
  }
</script>

<style scoped>
  .page-on-build {
    min-height: 100vh;
    display: flex;
    /* align-items: center; */
    justify-content: center;
    /* background: linear-gradient(135deg, #ecd02e 0%, #00704a 100%); */
    padding: 2rem;
  }

  .container {
    max-width: 800px;
    width: 100%;
  }

  .content-wrapper {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
  }

  .image-container {
    margin-bottom: 2rem;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .coming-soon-image {
    max-width: 100%;
    height: auto;
    max-height: 300px;
    object-fit: contain;
    border-radius: 10px;
  }

  .text-content {
    color: #333;
  }

  .title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #00704a  0%, #ecd02e 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .subtitle {
    font-size: 1.25rem;
    color: #4a5568;
    margin-bottom: 1rem;
    font-weight: 500;
  }

  .description {
    font-size: 1rem;
    color: #718096;
    margin-bottom: 2rem;
    line-height: 1.6;
  }

  .loading-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
  }

  .spinner {
    width: 24px;
    height: 24px;
    border: 3px solid #e2e8f0;
    border-top: 3px solid #00704a;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }

  .loading-text {
    color: #00704a;
    font-weight: 600;
    font-size: 0.9rem;
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }

  /* Responsive design */
  @media (max-width: 768px) {
    .page-on-build {
      padding: 1rem;
    }
    
    .content-wrapper {
      padding: 2rem 1.5rem;
    }
    
    .title {
      font-size: 2rem;
    }
    
    .subtitle {
      font-size: 1.1rem;
    }
    
    .coming-soon-image {
      max-height: 200px;
    }
  }

  @media (max-width: 480px) {
    .title {
      font-size: 1.75rem;
    }
    
    .subtitle {
      font-size: 1rem;
    }
    
    .content-wrapper {
      padding: 1.5rem 1rem;
    }
  }
</style>