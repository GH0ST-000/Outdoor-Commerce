import { describe, expect, it } from "vitest";
import {
  basisPointsToPercentLabel,
  formatMoneyMinor,
  formatMoneyRange,
  parseMajorToMinor,
  parsePercentToBasisPoints,
} from "@/features/pricing/lib/money";

describe("parseMajorToMinor", () => {
  it("parses valid GEL decimals to tetri", () => {
    expect(parseMajorToMinor("19.99")).toEqual({
      ok: true,
      amount_minor: 1999,
    });
    expect(parseMajorToMinor("100")).toEqual({
      ok: true,
      amount_minor: 10000,
    });
    expect(parseMajorToMinor("0.01")).toEqual({
      ok: true,
      amount_minor: 1,
    });
    expect(parseMajorToMinor("0")).toEqual({ ok: true, amount_minor: 0 });
  });

  it("rejects scientific notation and float-style abuse", () => {
    expect(parseMajorToMinor("1e2").ok).toBe(false);
    expect(parseMajorToMinor("1E3").ok).toBe(false);
  });

  it("rejects excessive decimals", () => {
    expect(parseMajorToMinor("19.999")).toEqual({
      ok: false,
      error: "too_many_decimals",
    });
  });

  it("rejects currency symbols and negatives", () => {
    expect(parseMajorToMinor("₾19.99").ok).toBe(false);
    expect(parseMajorToMinor("-5").ok).toBe(false);
  });

  it("does not use parseFloat multiplication", () => {
    // 1.29 * 100 via float is famously imprecise; our parser must yield 129.
    expect(parseMajorToMinor("1.29")).toEqual({
      ok: true,
      amount_minor: 129,
    });
  });
});

describe("formatMoneyMinor", () => {
  it("formats GEL for ka-GE and en", () => {
    const ka = formatMoneyMinor(12999, "GEL", "ka-GE");
    const en = formatMoneyMinor(12999, "GEL", "en");
    expect(ka).toMatch(/129/);
    expect(en).toMatch(/129/);
  });

  it("formats ranges", () => {
    const range = formatMoneyRange(12000, 18500, "GEL", "en");
    expect(range).toContain("–");
  });
});

describe("basis points", () => {
  it("maps percent input to basis points", () => {
    expect(parsePercentToBasisPoints("15")).toEqual({
      ok: true,
      amount_minor: 1500,
    });
    expect(parsePercentToBasisPoints("15.5")).toEqual({
      ok: true,
      amount_minor: 1550,
    });
  });

  it("labels basis points", () => {
    expect(basisPointsToPercentLabel(1500)).toBe("15%");
    expect(basisPointsToPercentLabel(1550)).toBe("15.5%");
  });
});
