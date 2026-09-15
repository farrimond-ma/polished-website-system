import React from 'react';
import { Routes, Route } from 'react-router-dom';
import Layout from './Layout';
import Home from './pages/Home';
import CoversHub from './pages/CoversHub';
import CoverPage from './pages/CoverPage';
import GetAQuote from './pages/GetAQuote';
import ThankYou from './pages/ThankYou';
import Guides from './pages/Guides';
import GuidePost from './pages/GuidePost';
import Questionnaire from './pages/Questionnaire';
import AboutUs from './pages/AboutUs';
import { PrivacyPolicy, TermsOfBusiness, Complaints, CookiePolicy } from './pages/Legal';
import NotFound from './pages/NotFound';
import './App.css';

function App() {
  return (
    <Routes>
      <Route path="/" element={<Layout />}>
        <Route index element={<Home />} />
        <Route path="cleaning-insurance" element={<CoversHub />} />
        <Route path="cleaning-insurance/:slug" element={<CoverPage />} />
        <Route path="get-a-quote" element={<GetAQuote />} />
        <Route path="get-a-quote/thank-you" element={<ThankYou />} />
        <Route path="get-a-quote/:slug" element={<GetAQuote />} />
        <Route path="guides" element={<Guides />} />
        <Route path="guides/:slug" element={<GuidePost />} />
        <Route path="insurance-questionnaire" element={<Questionnaire />} />
        <Route path="about-us" element={<AboutUs />} />
        <Route path="privacy-policy" element={<PrivacyPolicy />} />
        <Route path="terms-of-business" element={<TermsOfBusiness />} />
        <Route path="complaints" element={<Complaints />} />
        <Route path="cookie-policy" element={<CookiePolicy />} />
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  );
}

export default App;
