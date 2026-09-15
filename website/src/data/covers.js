// Cover pages: /cleaning-insurance/:slug, each with a matching enquiry page /get-a-quote/:slug.
// The content engine reads the slugs/titles from this file (via scripts/export-site-map.js)
// so generated guides can only link to pages that exist.
//
// kind: 'business' = a type of cleaning business, 'cover' = a type of insurance.
// Keep claims factual: no "cheapest", no guaranteed prices, no advice presented as personal.

export const covers = [
  // ───────────────────────────── Business types ─────────────────────────────
  {
    slug: 'contract-cleaners-insurance',
    kind: 'business',
    icon: 'building',
    title: 'Contract Cleaners Insurance',
    metaTitle: 'Contract Cleaners Insurance UK',
    metaDescription: 'Insurance for contract cleaning companies: public liability, employers\' liability, loss of keys, tools and equipment. Specialist cover arranged by UK brokers.',
    description: 'Cover built around commercial cleaning contracts: the liability limits your clients ask for, employers\' liability for your teams, and the extras that contract work really needs.',
    cardBlurb: 'For companies cleaning offices, schools, retail and industrial sites under contract.',
    intro: [
      'Contract cleaning companies carry more risk than most people realise. Your staff work unsupervised in other people\'s premises, often out of hours, with access keys, alarm codes and expensive equipment. One slip on a wet floor, a flooded server room or a lost master key can turn into a claim that runs into tens of thousands of pounds.',
      'We arrange insurance specifically for contract cleaners, from growing teams with a handful of contracts to established companies with hundreds of staff across multiple sites.',
    ],
    sections: [
      {
        heading: 'What cover does a contract cleaning company need?',
        paragraphs: ['Most contract cleaners build their policy around these core sections:'],
        list: [
          'Public liability: protects you if your work injures a member of the public or damages a client\'s property. Commercial contracts commonly require a limit of £5 million or £10 million.',
          'Employers\' liability: a legal requirement for most businesses with employees, with a minimum limit of £5 million. Policies are usually written at £10 million.',
          'Loss of keys: pays for replacement locks and keys if a key or fob you hold for a client is lost or stolen.',
          'Damage to property worked upon: covers damage to the surface or item you are actually cleaning, which a standard liability policy may exclude.',
          'Tools and equipment: protects scrubber driers, vacuums, carpet machines and pressure washers against theft and accidental damage.',
          'Fidelity guarantee: covers theft by your employees from your clients, often requested in tenders for banks, offices and schools.',
        ],
      },
      {
        heading: 'Meeting the insurance terms in your cleaning contracts',
        paragraphs: [
          'Before you sign a contract, check the insurance clause. Facilities managers, local authorities and large corporates often specify minimum limits, ask to be named as an interested party, and require evidence of cover every year. Some also require cover for work in specific environments such as hospitals, airports or food production.',
          'Tell us what your contracts require and we will make sure the policy matches, so you are not caught out when a client asks for your certificate.',
        ],
      },
      {
        heading: 'Staff, subcontractors and employers\' liability',
        paragraphs: [
          'Cleaning is a people business, and the way you engage people affects your cover. Labour-only subcontractors who work under your direction are usually treated as employees for employers\' liability purposes. Bona fide subcontractors who supply their own equipment and insurance are treated differently, and insurers will often want to see their certificates.',
          'Getting wages, headcount and subcontractor payments right on your proposal matters: understating them can leave you underinsured when you need to claim.',
        ],
      },
    ],
    faqs: [
      { q: 'Is public liability insurance a legal requirement for contract cleaners?', a: 'No, public liability insurance is not a legal requirement in the UK. However, almost every commercial client will ask to see it before awarding a contract, and many specify a minimum limit of £5 million or £10 million.' },
      { q: 'Do I need employers\' liability insurance if I only use part-time staff?', a: 'Yes. Employers\' liability insurance is compulsory for most businesses that employ staff, including part-time, temporary and casual workers. There are limited exemptions, for example some family businesses where all employees are closely related to the owner, but these do not apply to limited companies.' },
      { q: 'Does my policy cover damage to the floor or carpet I am cleaning?', a: 'Not always. Many liability policies exclude damage to the part of the property you are working on. An extension for damage to property worked upon can be added, and it is worth having if you use machinery, chemicals or treatments.' },
      { q: 'How is the premium for contract cleaners insurance calculated?', a: 'Insurers mainly look at your turnover, wages, number of staff, the types of premises you clean, any high-risk work such as working at height, the limits you need and your claims history. That is why our questionnaire asks about each of these.' },
    ],
    related: ['commercial-cleaning-insurance', 'employers-liability-insurance-for-cleaners', 'loss-of-keys-insurance'],
  },
  {
    slug: 'commercial-cleaning-insurance',
    kind: 'business',
    icon: 'briefcase',
    title: 'Commercial & Office Cleaning Insurance',
    metaTitle: 'Commercial & Office Cleaning Insurance',
    metaDescription: 'Specialist insurance for commercial and office cleaning businesses. Public liability, employers\' liability, keys cover and more, arranged by cleaning insurance specialists.',
    description: 'Office, retail and commercial cleaners need cover that matches how you actually work: out-of-hours access, client keys and alarm codes, and staff on multiple sites.',
    cardBlurb: 'For office, shop, gym and commercial premises cleaners.',
    intro: [
      'Commercial cleaners are trusted with access to their clients\' premises, often when nobody else is there. That trust comes with responsibility for injuries, damage, lost keys and security breaches, and the right insurance protects your business when something goes wrong.',
    ],
    sections: [
      {
        heading: 'Typical claims for commercial cleaning businesses',
        list: [
          'A visitor slips on a freshly mopped floor where a warning sign was not in place.',
          'A cleaner knocks over a monitor or damages a client\'s IT equipment.',
          'A tap is left running and water damages the floor below.',
          'An office key fob is lost, and the client needs the building\'s access system recoded.',
          'An employee is injured using a floor machine and makes a claim against you.',
        ],
      },
      {
        heading: 'The core covers',
        paragraphs: [
          'Most commercial cleaning policies combine public liability, employers\' liability, loss of keys cover and cover for your equipment. If your clients include banks, jewellers or offices holding sensitive information, fidelity guarantee (employee dishonesty) cover is also worth considering.',
          'If you hold stock, equipment or run an office or unit, you can add cover for your premises, contents and business interruption to the same policy.',
        ],
      },
      {
        heading: 'Growing your commercial cleaning business',
        paragraphs: [
          'As you win larger contracts, the insurance requirements usually go up too. We can review your cover as your turnover and team grow, so your limits keep pace with the contracts you are tendering for.',
        ],
      },
    ],
    faqs: [
      { q: 'What public liability limit do office cleaners need?', a: 'There is no single answer. Smaller office contracts may ask for £1 million or £2 million, while facilities management companies and public sector buyers frequently ask for £5 million or £10 million. Always check your contract.' },
      { q: 'Can I get insurance if I have just started a commercial cleaning company?', a: 'Yes. Insurers do insure new cleaning businesses. They will ask about your experience in the industry, your expected turnover and the type of premises you plan to clean.' },
      { q: 'Are my cleaning chemicals covered?', a: 'Your liability cover responds to injury or damage caused by your work, which can include chemical use. Insurers will expect you to follow COSHH regulations and manufacturer instructions.' },
    ],
    related: ['contract-cleaners-insurance', 'public-liability-insurance-for-cleaners', 'loss-of-keys-insurance'],
  },
  {
    slug: 'domestic-cleaners-insurance',
    kind: 'business',
    icon: 'home',
    title: 'Domestic Cleaners Insurance',
    metaTitle: 'Domestic Cleaners Insurance UK',
    metaDescription: 'Insurance for domestic cleaners and home cleaning businesses: public liability, loss of keys, equipment and employers\' liability if you take on staff.',
    description: 'Protection for cleaners working in private homes, from sole traders to domestic cleaning agencies with a team of cleaners.',
    cardBlurb: 'For self-employed home cleaners and domestic cleaning agencies.',
    intro: [
      'Working in people\'s homes means working around their belongings, pets, flooring and furniture. Most jobs go smoothly, but an accident such as a broken ornament, a stained sofa or a lost house key can quickly become an expensive dispute. Insurance gives your customers confidence and protects your income.',
    ],
    sections: [
      {
        heading: 'Insurance for self-employed domestic cleaners',
        paragraphs: [
          'If you work on your own, public liability insurance is the cover most customers expect you to have. It pays compensation and legal costs if you accidentally injure someone or damage their property. Adding loss of keys cover is sensible if you hold keys for regular clients.',
        ],
      },
      {
        heading: 'Insurance for domestic cleaning agencies and teams',
        paragraphs: [
          'Once you employ cleaners, employers\' liability insurance usually becomes a legal requirement. Agencies should also think carefully about how their cleaners are engaged, because self-employed cleaners placed through an agency may still need to be declared.',
        ],
        list: [
          'Public liability for injury and damage in customers\' homes',
          'Employers\' liability for your staff',
          'Loss of keys and replacement locks',
          'Theft by employees (fidelity guarantee)',
          'Tools and equipment, including items left in your vehicle',
        ],
      },
    ],
    faqs: [
      { q: 'Do domestic cleaners legally need insurance?', a: 'Sole traders with no employees are not legally required to hold insurance. Employers\' liability becomes compulsory once you employ people. Public liability is optional but strongly recommended, and many customers ask for it.' },
      { q: 'Am I covered if I break something in a customer\'s home?', a: 'Public liability insurance covers accidental damage to third-party property. Some policies limit cover for damage to items you are cleaning or handling, so check whether damage to property worked upon is included.' },
      { q: 'What if a customer accuses me of theft?', a: 'Liability insurance does not cover theft. Fidelity guarantee insurance can cover dishonesty by your employees. Keeping records of keys and clear procedures also helps protect you.' },
    ],
    related: ['end-of-tenancy-cleaning-insurance', 'public-liability-insurance-for-cleaners', 'loss-of-keys-insurance'],
  },
  {
    slug: 'window-cleaners-insurance',
    kind: 'business',
    icon: 'window',
    title: 'Window Cleaners Insurance',
    metaTitle: 'Window Cleaners Insurance UK',
    metaDescription: 'Insurance for window cleaners: public liability, employers\' liability, tools and ladders, water-fed pole systems and working at height. Specialist UK cover.',
    description: 'Cover for traditional and water-fed pole window cleaners, including the working-at-height questions insurers will ask.',
    cardBlurb: 'For residential and commercial window cleaners, including water-fed pole systems.',
    intro: [
      'Window cleaning is one of the higher-risk trades in the cleaning sector because much of the work is carried out at height. Insurers want to understand how you work, how high you go and what access equipment you use, so the right questions up front help you get cover that will actually pay out.',
    ],
    sections: [
      {
        heading: 'Working at height and your insurance',
        paragraphs: [
          'Your insurer will ask for the maximum height you work at and the methods you use, such as ladders, water-fed poles, mobile elevating work platforms (MEWPs) or rope access. Work above certain heights, or using cradles and rope access, is usually rated differently or needs specialist cover.',
          'Many window cleaners now use water-fed poles to work from the ground, which can reduce the risk profile. If that applies to you, make sure it is reflected in your proposal.',
        ],
      },
      {
        heading: 'Covers window cleaners commonly choose',
        list: [
          'Public liability: for damage to windows, frames, conservatories, vehicles or injury to passers-by.',
          'Employers\' liability: compulsory once you employ anyone, including casual help.',
          'Tools and equipment: water-fed pole systems, pure water units, ladders and vans\' fitted equipment.',
          'Personal accident: an income benefit if you are injured and cannot work.',
        ],
      },
    ],
    faqs: [
      { q: 'Does window cleaners insurance cover working on ladders?', a: 'Policies can cover ladder work, but insurers will ask about maximum working heights and your safety procedures. Always declare how you work, as undeclared height work can affect a claim.' },
      { q: 'Is my water-fed pole system covered if it is stolen from my van?', a: 'It can be, if you add tools and equipment cover that includes theft from vehicles. Insurers usually require the equipment to be locked away and the vehicle secured, and some limit cover overnight.' },
      { q: 'Do I need employers\' liability if my helper is family?', a: 'There are limited exemptions for family businesses where employees are closely related to the owner, but they do not apply if your business is a limited company. If in doubt, speak to us.' },
    ],
    related: ['tools-and-equipment-insurance-for-cleaners', 'public-liability-insurance-for-cleaners', 'pressure-washing-insurance'],
  },
  {
    slug: 'carpet-cleaners-insurance',
    kind: 'business',
    icon: 'carpet',
    title: 'Carpet & Upholstery Cleaners Insurance',
    metaTitle: 'Carpet Cleaners Insurance UK',
    metaDescription: 'Insurance for carpet and upholstery cleaners, covering shrinkage, colour run and damage to property worked upon, plus liability and equipment cover.',
    description: 'Specialist cover for carpet, rug and upholstery cleaners, including damage to the items you are treating.',
    cardBlurb: 'For carpet, rug and upholstery cleaning specialists.',
    intro: [
      'Carpet and upholstery cleaners use heat, water, chemicals and powerful machinery on expensive items that customers care about. Shrinkage, colour run, water damage and chemical reactions are genuine risks, and a standard liability policy may not cover damage to the carpet or sofa you are working on.',
    ],
    sections: [
      {
        heading: 'Why damage to property worked upon matters',
        paragraphs: [
          'Liability policies often exclude damage to "that part of the property being worked upon". For a carpet cleaner, that is the carpet itself. An extension for damage to property worked upon, or treatment risks, is what responds if a rug shrinks or a sofa discolours after cleaning.',
          'Insurers will also ask whether you take items away for cleaning, as property in your care, custody and control needs to be covered while it is off site.',
        ],
      },
      {
        heading: 'Other covers to consider',
        list: [
          'Public liability for injuries and third-party damage, such as water leaking through to a ceiling below',
          'Tools and equipment for truck-mounts, portable extractors and dryers',
          'Goods in transit if you collect rugs and upholstery',
          'Employers\' liability if you employ technicians',
        ],
      },
    ],
    faqs: [
      { q: 'Am I covered if a carpet shrinks after cleaning?', a: 'Only if your policy includes cover for damage to property worked upon or a treatment risks extension. Without it, damage to the carpet you are cleaning is often excluded.' },
      { q: 'Does insurance cover rugs I take back to my workshop?', a: 'You will need cover for customers\' goods in your custody, and goods in transit while they are in your vehicle. Tell us if you offer collection and off-site cleaning.' },
    ],
    related: ['oven-cleaners-insurance', 'tools-and-equipment-insurance-for-cleaners', 'domestic-cleaners-insurance'],
  },
  {
    slug: 'end-of-tenancy-cleaning-insurance',
    kind: 'business',
    icon: 'key',
    title: 'End of Tenancy Cleaning Insurance',
    metaTitle: 'End of Tenancy Cleaning Insurance',
    metaDescription: 'Insurance for end of tenancy and deep cleaning businesses: liability, loss of keys, equipment and employers\' liability for your cleaning teams.',
    description: 'Cover for end of tenancy, move-in and deep cleaning businesses working for tenants, landlords and letting agents.',
    cardBlurb: 'For end of tenancy, move-in and deep cleaning businesses.',
    intro: [
      'End of tenancy cleaning combines heavy-duty deep cleaning with tight deadlines, empty properties and keys collected from letting agents. The risks include damage to fixtures, chemical staining, water leaks and lost keys, and letting agents increasingly ask to see insurance before they add you to their supplier list.',
    ],
    sections: [
      {
        heading: 'Key risks for end of tenancy cleaners',
        list: [
          'Damage to worktops, flooring, ovens or bathroom fittings during deep cleaning',
          'Losing keys collected from agents, landlords or tenants',
          'Water left running or leaks from equipment in empty properties',
          'Injuries to staff lifting, climbing or using strong chemicals',
        ],
      },
      {
        heading: 'Building your cover',
        paragraphs: [
          'A typical policy includes public liability, loss of keys and cover for your equipment, with employers\' liability once you take on staff. If you also carry out carpet or oven cleaning as part of the service, tell us, because those activities can affect the cover you need.',
        ],
      },
    ],
    faqs: [
      { q: 'Do letting agents require cleaners to have insurance?', a: 'Many do. Agents commonly ask for public liability insurance and sometimes a minimum limit, because they are responsible to landlords for the contractors they send into properties.' },
      { q: 'Does loss of keys cover include changing locks?', a: 'Loss of keys cover typically pays for replacement keys and changing locks when keys you are responsible for are lost or stolen, up to the policy limit.' },
    ],
    related: ['domestic-cleaners-insurance', 'loss-of-keys-insurance', 'specialist-cleaning-insurance'],
  },
  {
    slug: 'oven-cleaners-insurance',
    kind: 'business',
    icon: 'oven',
    title: 'Oven Cleaners Insurance',
    metaTitle: 'Oven Cleaners Insurance UK',
    metaDescription: 'Insurance for oven cleaning businesses and franchisees: liability, damage to appliances worked upon, chemicals, equipment and vans.',
    description: 'Cover for oven, hob and extractor cleaning specialists, including the risk of damage to the appliances you work on.',
    cardBlurb: 'For oven, range and extractor cleaning specialists and franchisees.',
    intro: [
      'Oven cleaners work with caustic chemicals, dip tanks and delicate appliance components in customers\' kitchens. Damage to an expensive range cooker, a chemical burn to a worktop or a fault after reassembly can all lead to claims.',
    ],
    sections: [
      {
        heading: 'What oven cleaning insurance should include',
        list: [
          'Public liability for damage to kitchens and injury to customers',
          'Damage to property worked upon, so the appliance itself is covered',
          'Tools and equipment including heated dip tanks and van systems',
          'Employers\' liability if you employ technicians',
        ],
        paragraphs: [
          'If you are part of a franchise network, check whether the franchisor requires particular limits or a named interest on your policy.',
        ],
      },
    ],
    faqs: [
      { q: 'Am I covered if an oven stops working after I clean it?', a: 'Claims for damage to the appliance you worked on generally need a damage to property worked upon extension. Faults unrelated to your work are not covered, so good records and photos before and after each job help.' },
      { q: 'Does my insurance cover the chemicals I use?', a: 'Liability cover responds to accidental injury or damage caused by your work, including chemical use, provided you follow safe working practices and COSHH requirements.' },
    ],
    related: ['carpet-cleaners-insurance', 'domestic-cleaners-insurance', 'tools-and-equipment-insurance-for-cleaners'],
  },
  {
    slug: 'pressure-washing-insurance',
    kind: 'business',
    icon: 'spray',
    title: 'Pressure Washing & Driveway Cleaning Insurance',
    metaTitle: 'Pressure Washing Insurance UK',
    metaDescription: 'Insurance for pressure washing, driveway, roof and exterior cleaning businesses: liability, damage to surfaces, equipment and working at height.',
    description: 'Cover for driveway, patio, render, roof and exterior cleaning businesses using pressure washers and soft-wash systems.',
    cardBlurb: 'For driveway, patio, render, roof and exterior cleaning.',
    intro: [
      'Exterior cleaning uses high-pressure water and chemicals on surfaces that can be damaged surprisingly easily. Etched block paving, damaged render, water getting into a property, overspray onto cars and slippery runoff are all common sources of claims.',
    ],
    sections: [
      {
        heading: 'Risks insurers will ask about',
        list: [
          'Roof and gutter cleaning, and the maximum height you work at',
          'Soft washing and the chemicals you use',
          'Damage to the surfaces you clean, including render and cladding',
          'Water ingress into buildings and runoff onto neighbouring property',
        ],
        paragraphs: [
          'Cladding, roofing and high-rise work are often treated as high-risk activities, so declare them clearly to make sure your policy covers them.',
        ],
      },
      {
        heading: 'Protecting your equipment',
        paragraphs: [
          'Hot-water pressure washers, surface cleaners, water tanks and trailer-mounted systems are valuable and attractive to thieves. Tools and equipment cover can include theft from locked vehicles and trailers, subject to the policy\'s security conditions.',
        ],
      },
    ],
    faqs: [
      { q: 'Does pressure washing insurance cover damage to block paving?', a: 'Damage to the surface you are cleaning may need a damage to property worked upon extension. Standard public liability usually covers damage to other third-party property, such as a neighbour\'s car.' },
      { q: 'Can I get cover for roof cleaning?', a: 'Yes, but insurers will want details of the access methods and heights involved. Roof work is often rated differently from ground-level cleaning.' },
    ],
    related: ['window-cleaners-insurance', 'tools-and-equipment-insurance-for-cleaners', 'public-liability-insurance-for-cleaners'],
  },
  {
    slug: 'specialist-cleaning-insurance',
    kind: 'business',
    icon: 'shield',
    title: 'Specialist & Deep Cleaning Insurance',
    metaTitle: 'Specialist Cleaning Insurance UK',
    metaDescription: 'Insurance for specialist cleaning businesses: deep cleans, builders cleans, kitchen extraction, biohazard, hoarder and industrial cleaning.',
    description: 'For higher-risk and specialist work, from builders cleans and kitchen extract systems to trauma, biohazard and industrial cleaning.',
    cardBlurb: 'For builders cleans, kitchen extraction, biohazard and industrial cleaning.',
    intro: [
      'Specialist cleaning covers a wide range of work, and insurers treat each activity differently. The more clearly you describe what you do, where you do it and the percentage of your work in each area, the more accurate and reliable your cover will be.',
    ],
    sections: [
      {
        heading: 'Activities we can help insure',
        list: [
          'Builders and sparkle cleans on construction sites',
          'Kitchen extraction and ductwork cleaning',
          'Trauma, biohazard, needle sweep and hoarder cleans',
          'Industrial cleaning in factories and warehouses',
          'Graffiti removal and chewing gum removal',
          'Healthcare, laboratory and cleanroom cleaning',
        ],
      },
      {
        heading: 'High-risk locations and activities',
        paragraphs: [
          'Insurers ask specifically about work at airports, rail sites, nuclear and power generation sites, petrochemical premises, offshore locations and any work involving asbestos or hazardous substances. Some of these can be covered with the right insurer; others need specialist arrangements. Our questionnaire asks about each one so nothing is missed.',
        ],
      },
    ],
    faqs: [
      { q: 'Is biohazard cleaning covered by standard cleaning insurance?', a: 'Often not. Trauma and biohazard work usually needs to be declared specifically, and insurers will ask about your training, procedures and waste disposal arrangements.' },
      { q: 'Does cleaning insurance cover asbestos?', a: 'Asbestos is commonly excluded or restricted. Some policies offer a limited asbestos buyback for non-licensed work. Always tell us if there is any chance of exposure.' },
    ],
    related: ['contract-cleaners-insurance', 'employers-liability-insurance-for-cleaners', 'end-of-tenancy-cleaning-insurance'],
  },

  // ───────────────────────────── Cover types ─────────────────────────────
  {
    slug: 'public-liability-insurance-for-cleaners',
    kind: 'cover',
    icon: 'shield',
    title: 'Public Liability Insurance for Cleaners',
    metaTitle: 'Public Liability Insurance for Cleaners',
    metaDescription: 'Public liability insurance for UK cleaning businesses. What it covers, what limits clients ask for, and how to get the right cover for your cleaning work.',
    description: 'The cover your clients ask for first: protection if your cleaning work injures someone or damages their property.',
    cardBlurb: 'Injury to the public and damage to third-party property.',
    intro: [
      'Public liability insurance pays compensation and legal costs if someone makes a claim against your business for injury or property damage caused by your work. For cleaners, that might be a slip on a wet floor, a broken fixture, or water damage from a leak.',
    ],
    sections: [
      {
        heading: 'What public liability covers',
        list: [
          'Injury to customers, visitors and members of the public',
          'Accidental damage to client or third-party property',
          'Legal defence costs, even if the claim is unsuccessful',
          'Products liability for items or treatments you supply',
        ],
      },
      {
        heading: 'Choosing your limit of indemnity',
        paragraphs: [
          'The limit is the most the insurer will pay for any one claim. Domestic customers rarely specify a limit, but commercial contracts often ask for £1 million, £2 million, £5 million or £10 million. Choose a limit that satisfies your largest contract and reflects the potential cost of a serious injury.',
        ],
      },
      {
        heading: 'Useful extensions for cleaners',
        list: [
          'Damage to property worked upon',
          'Loss of keys',
          'Temporary removal of customers\' property for cleaning',
          'Loss of metered water',
          'Trace and access, to find the source of a leak',
        ],
      },
    ],
    faqs: [
      { q: 'Is public liability insurance compulsory for cleaners?', a: 'No, it is not a legal requirement. It is, however, one of the most commonly requested covers in cleaning contracts and tenders.' },
      { q: 'What is the difference between public liability and employers\' liability?', a: 'Public liability covers claims from third parties such as customers and the public. Employers\' liability covers claims from your own employees who are injured or become ill because of their work.' },
    ],
    related: ['employers-liability-insurance-for-cleaners', 'loss-of-keys-insurance', 'contract-cleaners-insurance'],
  },
  {
    slug: 'employers-liability-insurance-for-cleaners',
    kind: 'cover',
    icon: 'people',
    title: 'Employers\' Liability Insurance for Cleaning Companies',
    metaTitle: 'Employers\' Liability Insurance for Cleaners',
    metaDescription: 'Employers\' liability insurance for cleaning businesses: the legal requirement, who counts as an employee, subcontractors and the £5 million minimum.',
    description: 'Compulsory for most cleaning businesses with staff. Protects you if an employee is injured or made ill by their work.',
    cardBlurb: 'The legal requirement once you employ cleaners.',
    intro: [
      'Under the Employers\' Liability (Compulsory Insurance) Act 1969, most businesses that employ people must hold employers\' liability insurance with a limit of at least £5 million. In practice most policies are written at £10 million.',
      'The Health and Safety Executive can fine businesses up to £2,500 for every day they do not have suitable cover.',
    ],
    sections: [
      {
        heading: 'Who counts as an employee?',
        paragraphs: [
          'Employees include full-time, part-time, temporary and casual staff. For insurance purposes, labour-only subcontractors who work under your direction and use your equipment are usually treated as employees too. Bona fide subcontractors who supply their own materials and insurance are normally treated separately.',
        ],
      },
      {
        heading: 'Common employee claims in cleaning',
        list: [
          'Slips, trips and falls',
          'Manual handling and back injuries',
          'Dermatitis and respiratory problems from cleaning chemicals',
          'Falls from ladders and steps',
          'Hand-arm vibration from floor machines',
        ],
      },
      {
        heading: 'Your Employer Reference Number (ERN)',
        paragraphs: [
          'Insurers ask for your Employer Reference Number, issued by HMRC when you register as an employer. It helps former employees trace your insurer in the future, particularly for long-tail illness claims.',
        ],
      },
    ],
    faqs: [
      { q: 'What is the minimum employers\' liability limit?', a: 'The legal minimum is £5 million, although most insurers provide £10 million as standard.' },
      { q: 'Do I need employers\' liability if I am a sole trader with no staff?', a: 'No. If you have no employees you are generally exempt. Once you take on staff, including casual workers, you usually need it.' },
      { q: 'Do I need to display my employers\' liability certificate?', a: 'You must make your certificate available to employees, either by displaying it or making it accessible electronically. You can be fined for failing to do so.' },
    ],
    related: ['public-liability-insurance-for-cleaners', 'contract-cleaners-insurance', 'specialist-cleaning-insurance'],
  },
  {
    slug: 'tools-and-equipment-insurance-for-cleaners',
    kind: 'cover',
    icon: 'tools',
    title: 'Tools & Equipment Insurance for Cleaners',
    metaTitle: 'Cleaning Equipment Insurance',
    metaDescription: 'Insurance for cleaning equipment: floor machines, carpet cleaners, pressure washers and water-fed pole systems against theft and accidental damage.',
    description: 'Protect the machines and equipment your business depends on against theft, fire and accidental damage.',
    cardBlurb: 'Machines, vacuums, pressure washers and pole systems.',
    intro: [
      'Losing your equipment means losing working days. Tools and equipment cover pays to repair or replace cleaning machinery and kit that is stolen or damaged, whether it is in use on site, stored at your premises or kept in your vehicle.',
    ],
    sections: [
      {
        heading: 'What can be covered',
        list: [
          'Scrubber driers, polishers and floor machines',
          'Carpet and upholstery extraction machines',
          'Pressure washers and surface cleaners',
          'Water-fed pole systems and pure water units',
          'Hired-in equipment you are responsible for',
        ],
      },
      {
        heading: 'Security conditions to be aware of',
        paragraphs: [
          'Most policies have conditions for theft from vehicles, such as the vehicle being locked with keys removed, and some restrict cover overnight unless the vehicle is garaged. Check these carefully and tell us how your equipment is stored.',
        ],
      },
    ],
    faqs: [
      { q: 'Is equipment stolen from my van covered?', a: 'It can be, if your policy includes theft from vehicles. Insurers usually require signs of forced entry and may apply overnight restrictions.' },
      { q: 'Should I insure equipment for new or second-hand value?', a: 'That depends on the policy basis. Some pay new-for-old, others deduct for wear and tear. Tell us the replacement cost so the sum insured is accurate.' },
    ],
    related: ['window-cleaners-insurance', 'carpet-cleaners-insurance', 'pressure-washing-insurance'],
  },
  {
    slug: 'loss-of-keys-insurance',
    kind: 'cover',
    icon: 'key',
    title: 'Loss of Keys Insurance for Cleaners',
    metaTitle: 'Loss of Keys Insurance for Cleaning Businesses',
    metaDescription: 'Loss of keys cover for cleaning businesses: replacement keys, fobs and locks when client keys in your care are lost or stolen.',
    description: 'If a client key, fob or access card in your care goes missing, this cover pays for replacement keys and locks.',
    cardBlurb: 'Replacement keys, fobs and locks for client premises.',
    intro: [
      'Cleaners regularly hold keys to homes, offices and commercial buildings. Replacing a single door lock is inexpensive, but losing a master key for an office block or a fob for a building access system can mean re-keying an entire site.',
    ],
    sections: [
      {
        heading: 'What loss of keys cover pays for',
        list: [
          'Replacement keys, fobs and access cards',
          'Changing or recoding locks and access systems',
          'Temporary security measures while locks are replaced, depending on the policy',
        ],
      },
      {
        heading: 'Reducing the risk',
        paragraphs: [
          'Keep a key register, avoid labelling keys with client addresses, and use coded tags and a lockable key safe. Insurers will expect reasonable precautions, and good processes also reassure clients when you tender.',
        ],
      },
    ],
    faqs: [
      { q: 'Is loss of keys included in public liability insurance?', a: 'Not automatically. Loss of keys is usually an extension to your liability policy, with its own limit, so check that it is included and the limit is high enough for your largest client.' },
      { q: 'What limit of keys cover do I need?', a: 'Consider the most expensive site you hold keys for. Master key systems and electronic access control can be far more costly to replace than domestic locks.' },
    ],
    related: ['commercial-cleaning-insurance', 'domestic-cleaners-insurance', 'public-liability-insurance-for-cleaners'],
  },
  {
    slug: 'professional-indemnity-insurance-for-cleaning-companies',
    kind: 'cover',
    icon: 'document',
    title: 'Professional Indemnity for Cleaning & Facilities Companies',
    metaTitle: 'Professional Indemnity for Cleaning Companies',
    metaDescription: 'Professional indemnity insurance for cleaning and facilities companies that provide advice, specifications, audits or hygiene consultancy.',
    description: 'For cleaning and facilities businesses that give advice, write specifications or carry out audits and inspections.',
    cardBlurb: 'For advice, specifications, audits and consultancy.',
    intro: [
      'Most cleaning businesses do not need professional indemnity insurance. It becomes relevant when you are paid for your advice or expertise, for example writing cleaning specifications, carrying out hygiene audits, advising on infection control or managing other contractors.',
    ],
    sections: [
      {
        heading: 'When professional indemnity is worth considering',
        list: [
          'You design cleaning specifications or schedules for clients',
          'You carry out hygiene, infection control or compliance audits',
          'You provide facilities management and manage subcontractors',
          'A contract or tender specifically requires it',
        ],
        paragraphs: [
          'Professional indemnity covers claims that your advice or professional service was negligent and caused your client a financial loss. It is different from public liability, which covers injury and physical damage.',
        ],
      },
    ],
    faqs: [
      { q: 'Do cleaners need professional indemnity insurance?', a: 'Usually not, unless you provide advice, design or consultancy services, or a client contract requires it.' },
      { q: 'What does retroactive date mean?', a: 'Professional indemnity is written on a claims-made basis. The retroactive date is the earliest date from which work is covered, so keeping continuous cover matters.' },
    ],
    related: ['contract-cleaners-insurance', 'commercial-cleaning-insurance', 'employers-liability-insurance-for-cleaners'],
  },
];

export const coversBySlug = Object.fromEntries(covers.map((c) => [c.slug, c]));
export const businessCovers = covers.filter((c) => c.kind === 'business');
export const coverTypes = covers.filter((c) => c.kind === 'cover');
