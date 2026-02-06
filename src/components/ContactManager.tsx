import React, { useState, useEffect } from 'react';
import logger from '../core/logger.js';
import { useContact } from '../hooks/useContact.js';
import { BusinessInfo } from './contact/BusinessInfo.js';
import { ContactForm } from './contact/ContactForm.js';
import { CaptchaModal } from './modals/contact/CaptchaModal.js';

import { PAGE } from '../core/constants.js';
import { IContactData } from '../types/pages.js';

interface ContactManagerProps {
    businessData?: IContactData;
}

/**
 * ContactManager Component
 * Orchestrates the "Contact Us" page, including business info reveal and the contact form.
 * Replaces: contact.php UI
 */
export const ContactManager: React.FC<ContactManagerProps> = ({ businessData }) => {
    const {
        isSubmitting,
        status,
        setStatus,
        captcha,
        isCaptchaOpen,
        setIsCaptchaOpen,
        generateCaptcha,
        submitForm
    } = useContact();

    const [formData, setFormData] = useState({
        name: '',
        email: '',
        subject: '',
        message: '',
        website: '', // Honeypot
        csrf: businessData?.csrf || ''
    });

    const [captchaValue, setCaptchaValue] = useState('');
    const [revealOpen, setRevealOpen] = useState(false);
    const [revealedDetails, setRevealedDetails] = useState<IContactData | null>(null);

    // Sync CSRF when businessData matches (after hydration)
    useEffect(() => {
        if (businessData?.csrf) {
            setFormData(prev => ({ ...prev, csrf: businessData.csrf || '' }));
        }
    }, [businessData]);

    const page = document.body.getAttribute('data-page');

    if (page !== PAGE.CONTACT) {
        return null;
    }

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));
        if (status) setStatus(null);
    };

    const handleFormSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        // Basic validation
        if (!formData.name.trim() || !formData.email.trim() || !formData.message.trim()) {
            setStatus({ message: 'Please fill in all required fields.', type: 'error' });
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
            setStatus({ message: 'Please enter a valid email address.', type: 'error' });
            return;
        }

        setRevealOpen(false); // Ensure we are in form mode
        generateCaptcha();
    };

    const handleCaptchaSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        // Use loose equality and string conversion for robust comparison
        const isMatch = captchaValue.trim() === captcha?.expected?.toString();

        if (isMatch) {
            if (revealOpen) {
                const details = getBusinessDetails();
                setRevealedDetails(details);
            } else {
                setIsCaptchaOpen(false);
                await submitForm(formData);
                // Reset form but PRESERVE CSRF
                setFormData({
                    name: '',
                    email: '',
                    subject: '',
                    message: '',
                    website: '',
                    csrf: businessData?.csrf || formData.csrf
                });
            }
            setCaptchaValue('');
        } else {
            setStatus({ message: 'Incorrect check, please try again.', type: 'error' });
            setCaptchaValue('');
        }
    };

    const handleRevealClick = () => {
        setRevealOpen(true);
        setRevealedDetails(null);
        generateCaptcha();
    };

    const b64Decode = (str: string) => {
        if (!str) return '';
        try {
            // First attempt: Standard atob with UTF-8 support
            return decodeURIComponent(escape(atob(str)));
        } catch (_) {
            try {
                // Second attempt: Standard atob
                return atob(str);
            } catch (err) {
                // Final fallback: Return as-is if not valid base64
                logger.warn('[ContactManager] b64Decode failed, returning raw string', { str, error: err });
                return str;
            }
        }
    };

    const getBusinessDetails = (): IContactData | null => {
        if (!businessData) return null;
        return {
            email: businessData.email ? b64Decode(businessData.email) : '',
            phone: businessData.phone ? b64Decode(businessData.phone) : '',
            address: businessData.address ? b64Decode(businessData.address) : '',
            owner: businessData.owner ? b64Decode(businessData.owner) : '',
            name: businessData.name ? b64Decode(businessData.name) : '',
            site: businessData.site ? b64Decode(businessData.site) : '',
            hours: businessData.hours ? b64Decode(businessData.hours) : '',
            page_title: businessData.page_title,
            page_intro: businessData.page_intro,
            csrf: businessData.csrf
        };
    };

    const handleCloseModal = () => {
        setIsCaptchaOpen(false);
        setRevealOpen(false);
        setRevealedDetails(null);
    };

    return (
        <div className="max-w-3xl px-4 py-0 space-y-2" style={{ marginLeft: '150px' }}>
            <BusinessInfo
                revealed={revealedDetails !== null}
                onReveal={handleRevealClick}
            />

            <ContactForm
                formData={formData}
                onInputChange={handleInputChange}
                onSubmit={handleFormSubmit}
                isSubmitting={isSubmitting}
                status={status}
                pageTitle={businessData?.page_title}
                pageIntro={businessData?.page_intro}
            />

            <CaptchaModal
                isOpen={isCaptchaOpen}
                onClose={handleCloseModal}
                onSubmit={handleCaptchaSubmit}
                captchaValue={captchaValue}
                setCaptchaValue={setCaptchaValue}
                captcha={captcha ? { a: captcha.a, b: captcha.b } : undefined}
                isRevealing={revealOpen}
                revealedDetails={revealedDetails}
            />
        </div>
    );
};
