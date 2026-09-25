/**
 * Demo photography for the storefront prototype.
 * Swap these for owned brand assets before launch.
 */
const u = (id: string, w = 1600) =>
  `https://images.unsplash.com/${id}?auto=format&fit=crop&w=${w}&q=80`;

export const storefrontMedia = {
  hero: u("photo-1464822759023-fed622ff2c3b", 2400),
  map: u("photo-1464822759023-fed622ff2c3b", 1600),
  categories: {
    hunting: u("photo-1448375240586-882707db888b"),
    fishing: u("photo-1439066615861-d1af74d74000"),
    camping: u("photo-1478131143081-80f7f84ca84d"),
    clothing: u("photo-1551632811-561732d1e306"),
    optics: u("photo-1519681393784-d120267933ba"),
    knives: u("photo-1593618998160-e34014e67546"),
  },
  products: {
    jacket: u("photo-1544022613-e87ca75a784a", 1200),
    optic: u("photo-1578662996442-48f60103fc96", 1200),
    line: u("photo-1544551763-46a013bb70d5", 1200),
    knife: u("photo-1565193566173-7a0ee3dbe261", 1200),
    jacketB: u("photo-1520975954732-35dd22299614", 1200),
    jacketC: u("photo-1551028719-00167b16eac5", 1200),
  },
  guides: {
    layering: u("photo-1551632811-561732d1e306", 1400),
    optics: u("photo-1469474968028-56623f02e42e", 1400),
    river: u("photo-1501785888041-af3ef285b470", 1400),
  },
  season: u("photo-1484406566174-9da000fda645", 2000),
  species: {
    roe: u("photo-1484406566174-9da000fda645", 1200),
    boar: u("photo-1441974231531-c6227db76b6e", 1200),
    chamois: u("photo-1464822759023-fed622ff2c3b", 1200),
  },
} as const;
