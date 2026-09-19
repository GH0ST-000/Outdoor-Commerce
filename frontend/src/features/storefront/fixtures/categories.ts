import type { StorefrontCategory } from "@/features/storefront/types";

/**
 * DEMO DATA — hand-written placeholder catalogue structure.
 * Replace with the admin catalogue API (see adapters/) before launch.
 */
export const categoryFixtures: StorefrontCategory[] = [
  {
    slug: "hunting",
    name: { en: "Hunting", ka: "ნადირობა" },
    kicker: {
      en: "Stands, calls, packs",
      ka: "ბუდეები, მოსახმობები, ზურგჩანთები",
    },
    lede: {
      en: "Equipment for long approaches and patient mornings — load-bearing packs, calls, and seat systems for cold hours above the treeline.",
      ka: "აღჭურვილობა გრძელი მიდგომებისა და მოთმინებიანი დილებისთვის — დატვირთვის ზურგჩანთები, მოსახმობები და დასაჯდომი სისტემები ტყის ზოლს ზემოთ ცივი საათებისთვის.",
    },
    image: {
      src: "/storefront/category-hunting.svg",
      alt: {
        en: "Illustrated ridgeline at first light",
        ka: "ილუსტრირებული ქედი პირველი სინათლეზე",
      },
      width: 800,
      height: 1000,
    },
    emphasis: "primary",
    highlights: [
      {
        en: "Load-bearing packs and meat hauling frames",
        ka: "დატვირთვის ზურგჩანთები და ტვირთის ჩარჩოები",
      },
      {
        en: "Calls, decoys, and scent management",
        ka: "მოსახმობები, საცოცხლები და სუნის კონტროლი",
      },
      {
        en: "Insulated seats and glassing rests",
        ka: "იზოლირებული დასაჯდომები და ოპტიკის საყრდენები",
      },
    ],
  },
  {
    slug: "fishing",
    name: { en: "Fishing", ka: "თევზაობა" },
    kicker: { en: "Rods, lines, wading", ka: "ჯოხები, ბადეები, წყალში სვლა" },
    lede: {
      en: "Built for Georgian rivers that change character every kilometre — fast headwaters, braided gravel, and slow lowland bends.",
      ka: "შექმნილია ქართული მდინარეებისთვის, რომლებიც ყოველ კილომეტრზე იცვლიან ხასიათს — სწრაფი სათავეები, ხრეშიანი ტოტები და დაბლობის ნელი მოსახვევები.",
    },
    image: {
      src: "/storefront/category-fishing.svg",
      alt: {
        en: "Illustrated river channel between gravel bars",
        ka: "ილუსტრირებული მდინარის კალაპოტი ხრეშიან ზოლებს შორის",
      },
      width: 800,
      height: 1000,
    },
    emphasis: "secondary",
    highlights: [
      {
        en: "Travel rods and reels for headwater access",
        ka: "სამოგზაურო ჯოხები და რგოლები სათავეებისთვის",
      },
      {
        en: "Wading boots with cold-water traction",
        ka: "წყალში სვლის ფეხსაცმელი ცივი წყლის ჩაჭიდებით",
      },
      {
        en: "Line, leader, and terminal tackle",
        ka: "ბადე, ლიდერი და ბოლო აღკაზმულობა",
      },
    ],
  },
  {
    slug: "camping",
    name: { en: "Camping", ka: "კემპინგი" },
    kicker: { en: "Shelter, sleep, cook", ka: "თავშესაფარი, ძილი, სამზარეულო" },
    lede: {
      en: "Shelter and sleep systems rated for exposed camps, where wind matters more than temperature and every gram is carried on your back.",
      ka: "თავშესაფრისა და ძილის სისტემები ღია ბანაკებისთვის, სადაც ქარი ტემპერატურაზე მეტად მნიშვნელოვანია და ყოველი გრამი ზურგზე მიგაქვს.",
    },
    image: {
      src: "/storefront/category-camping.svg",
      alt: {
        en: "Illustrated tent silhouette under a high pass",
        ka: "ილუსტრირებული კარვის სილუეტი მაღალი უღელტეხილის ქვეშ",
      },
      width: 800,
      height: 1000,
    },
    emphasis: "secondary",
    highlights: [
      {
        en: "Four-season shelters and guy-line kits",
        ka: "ოთხსეზონიანი თავშესაფრები და ბაგირების ნაკრებები",
      },
      {
        en: "Sleep systems for freezing nights",
        ka: "ძილის სისტემები ყინვიანი ღამეებისთვის",
      },
      {
        en: "Stoves, fuel, and water treatment",
        ka: "ღუმელები, საწვავი და წყლის დამუშავება",
      },
    ],
  },
  {
    slug: "clothing",
    name: { en: "Clothing", ka: "ტანსაცმელი" },
    kicker: {
      en: "Layers, shells, boots",
      ka: "შრეები, გარეთა ფენები, ფეხსაცმელი",
    },
    lede: {
      en: "A layering system that works wet — merino next to skin, wind protection that packs small, and shells that stay quiet in cover.",
      ka: "შრეების სისტემა, რომელიც სველშიც მუშაობს — მერინო კანთან, ქარისგან დაცვა, რომელიც პატარად იკეცება, და გარეთა ფენები, რომლებიც საფარში ხმას არ იღებს.",
    },
    image: {
      src: "/storefront/category-clothing.svg",
      alt: {
        en: "Illustrated layered garment silhouette",
        ka: "ილუსტრირებული მრავალშრიანი ტანსაცმლის სილუეტი",
      },
      width: 800,
      height: 1000,
    },
    emphasis: "tertiary",
    highlights: [
      {
        en: "Merino base layers and mid-weight fleece",
        ka: "მერინოს ქვედა შრეები და საშუალო სიმძიმის ფლისი",
      },
      {
        en: "Quiet softshells for still hunting",
        ka: "მშვიდი სოფტშელები უძრავი ნადირობისთვის",
      },
      {
        en: "Mountain boots and gaiter systems",
        ka: "მთის ფეხსაცმელი და გეტრების სისტემები",
      },
    ],
  },
  {
    slug: "optics",
    name: { en: "Optics", ka: "ოპტიკა" },
    kicker: {
      en: "Binoculars, spotting, rangefinders",
      ka: "ბინოკლები, სადამკვირვებლო, მანძილმზომები",
    },
    lede: {
      en: "Glass is where patience pays. Wide fields for scanning ridges, and low-light performance for the twenty minutes that matter.",
      ka: "ოპტიკა არის იქ, სადაც მოთმინება ანაზღაურდება. ფართო ხედვის არეები ქედების დათვალიერებისთვის და დაბალი განათების მაჩვენებლები იმ ოცი წუთისთვის, რომელსაც მნიშვნელობა აქვს.",
    },
    image: {
      src: "/storefront/category-optics.svg",
      alt: {
        en: "Illustrated binocular silhouette with twin barrels",
        ka: "ილუსტრირებული ბინოკლის სილუეტი ორი მილით",
      },
      width: 800,
      height: 1000,
    },
    emphasis: "tertiary",
    highlights: [
      {
        en: "Full-size and compact binoculars",
        ka: "სრული და კომპაქტური ბინოკლები",
      },
      {
        en: "Spotting scopes and tripod heads",
        ka: "სადამკვირვებლო ოპტიკა და სამფეხის თავები",
      },
      {
        en: "Rangefinders and harness systems",
        ka: "მანძილმზომები და სამაგრი სისტემები",
      },
    ],
  },
  {
    slug: "knives-tools",
    name: { en: "Knives & Tools", ka: "დანები და ხელსაწყოები" },
    kicker: {
      en: "Blades, sharpening, repair",
      ka: "დანები, გალესვა, შეკეთება",
    },
    lede: {
      en: "Edges that hold through a full day of work, plus the sharpening and repair kit that keeps a trip going after something breaks.",
      ka: "პირები, რომლებიც სრული სამუშაო დღეს უძლებენ, და გალესვისა და შეკეთების ნაკრები, რომელიც მოგზაურობას აგრძელებს რაღაცის გატეხვის შემდეგ.",
    },
    image: {
      src: "/storefront/category-knives-tools.svg",
      alt: {
        en: "Illustrated fixed-blade knife silhouette",
        ka: "ილუსტრირებული ფიქსირებული პირის დანის სილუეტი",
      },
      width: 800,
      height: 1000,
    },
    emphasis: "tertiary",
    highlights: [
      {
        en: "Fixed blades and folding work knives",
        ka: "ფიქსირებული პირები და დასაკეცი სამუშაო დანები",
      },
      {
        en: "Field sharpening and honing kits",
        ka: "საველე გალესვისა და დახვეწის ნაკრებები",
      },
      {
        en: "Repair tape, cord, and spares",
        ka: "შესაკეთებელი ლენტი, ბაგირი და სათადარიგო ნაწილები",
      },
    ],
  },
];
