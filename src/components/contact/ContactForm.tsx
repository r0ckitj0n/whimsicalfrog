import React from 'react';

interface ContactFormProps {
    formData: {
        name: string;
        email: string;
        subject: string;
        message: string;
        website: string;
    };
    onInputChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => void;
    onSubmit: (e: React.FormEvent) => void;
    isSubmitting: boolean;
    status: { message: string; type: 'success' | 'error' | 'info' } | null;
    pageTitle?: string;
    pageIntro?: string;
}

export const ContactForm: React.FC<ContactFormProps> = ({
    formData,
    onInputChange,
    onSubmit,
    isSubmitting,
    status,
    pageTitle,
    pageIntro
}) => {
    return (
        <div className="wf-contact-card">
            <div className="prose max-w-none">
                <h1 className="text-3xl font-bold mb-4">{pageTitle || 'Contact Us'}</h1>
                <div className="content leading-relaxed mb-6">
                    {pageIntro ? (
                        <div dangerouslySetInnerHTML={{ __html: pageIntro }} />
                    ) : (
                        <p>Have a question or special request?<br />Send us a message and we'll get back to you soon.</p>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-8">
                    <div>
                        <form onSubmit={onSubmit} className="space-y-4 wf-contact-form" noValidate>
                            <input type="text" name="website" value={formData.website} onChange={onInputChange} className="wf-honeypot" tabIndex={-1} aria-hidden="true" />

                            <div>
                                <label htmlFor="name" className="block font-medium mb-1">Name</label>
                                <input
                                    id="name" name="name" type="text" required
                                    value={formData.name}
                                    onChange={onInputChange}
                                    className="w-full border rounded px-3 py-2 text-left"
                                    maxLength={100}
                                    autoComplete="name"
                                />
                            </div>

                            <div>
                                <label htmlFor="email" className="block font-medium mb-1">Email</label>
                                <input
                                    id="email" name="email" type="email" required
                                    value={formData.email}
                                    onChange={onInputChange}
                                    className="w-full border rounded px-3 py-2 text-left"
                                    maxLength={255}
                                    autoComplete="email"
                                />
                            </div>

                            <div>
                                <label htmlFor="subject" className="block font-medium mb-1">Subject (optional)</label>
                                <input
                                    id="subject" name="subject" type="text"
                                    value={formData.subject}
                                    onChange={onInputChange}
                                    className="w-full border rounded px-3 py-2 text-left"
                                    maxLength={150}
                                    autoComplete="off"
                                />
                            </div>

                            <div>
                                <label htmlFor="message" className="block font-medium mb-1">Message</label>
                                <textarea
                                    id="message" name="message" required
                                    value={formData.message}
                                    onChange={onInputChange}
                                    rows={5}
                                    className="w-full border rounded px-3 py-2 text-left"
                                    maxLength={5000}
                                    autoComplete="off"
                                />
                            </div>

                            <div className="text-left pt-2">
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="wf-submit-btn"
                                    id="wf-contact-submit"
                                >
                                    {isSubmitting ? 'Sending...' : 'Submit'}
                                </button>
                            </div>

                            {status && (
                                <p className={`text-sm ${status.type === 'error' ? 'text-red-200' : 'text-green-200'
                                    }`}>
                                    {status.message}
                                </p>
                            )}
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
};
