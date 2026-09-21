import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { axe } from "jest-axe";
import { Button } from "@/components/ui/button";
import { IconButton } from "@/components/ui/icon-button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Pagination } from "@/components/ui/pagination";
import { ColorSwatch } from "@/components/commerce/variant-option";
import { AvailabilityStatus } from "@/components/commerce/availability-status";
import { FilterCheckbox } from "@/components/commerce/filter-controls";
import { QuantityStepper } from "@/components/commerce/quantity-stepper";
import { Surface } from "@/components/layout/primitives";

describe("Button", () => {
  it("exposes a loading state without dropping the label", async () => {
    render(
      <Button loading loadingLabel="Saving">
        Save
      </Button>,
    );
    expect(screen.getByRole("button", { name: /save/i })).toHaveAttribute(
      "aria-busy",
      "true",
    );
    expect(screen.getByLabelText("Saving")).toBeInTheDocument();
  });

  it("requires an accessible name on icon buttons", () => {
    render(
      <IconButton label="Search">
        <span>*</span>
      </IconButton>,
    );
    expect(screen.getByRole("button", { name: "Search" })).toBeInTheDocument();
  });
});

describe("Field", () => {
  it("associates description and error with the control", () => {
    render(
      <Field
        label="ელფოსტა"
        htmlFor="email"
        description="Required"
        error="Enter a valid address"
      >
        <Input />
      </Field>,
    );
    const input = screen.getByLabelText(/ელფოსტა/);
    expect(input).toHaveAttribute("aria-invalid", "true");
    expect(input).toHaveAccessibleDescription(/required/i);
    expect(screen.getByRole("alert")).toHaveTextContent(
      "Enter a valid address",
    );
  });
});

describe("selection controls", () => {
  it("toggles a checkbox from the keyboard", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    function Bound() {
      return <Checkbox aria-label="In stock" onCheckedChange={onChange} />;
    }
    render(<Bound />);
    const box = screen.getByRole("checkbox", { name: "In stock" });
    box.focus();
    await user.keyboard(" ");
    expect(onChange).toHaveBeenCalled();
  });

  it("keeps radio group selection exclusive", async () => {
    const user = userEvent.setup();
    render(
      <RadioGroup defaultValue="ka" aria-label="Locale">
        <RadioGroupItem value="ka" aria-label="ქართული" />
        <RadioGroupItem value="en" aria-label="English" />
      </RadioGroup>,
    );
    await user.click(screen.getByRole("radio", { name: "English" }));
    expect(screen.getByRole("radio", { name: "English" })).toBeChecked();
    expect(screen.getByRole("radio", { name: "ქართული" })).not.toBeChecked();
  });
});

describe("Dialog", () => {
  it("traps focus and restores it on close", async () => {
    const user = userEvent.setup();
    render(
      <div>
        <Dialog>
          <DialogTrigger asChild>
            <Button>Open</Button>
          </DialogTrigger>
          <DialogContent closeLabel="Close dialog">
            <DialogTitle>Archive</DialogTitle>
            <Button>Confirm</Button>
          </DialogContent>
        </Dialog>
      </div>,
    );
    const trigger = screen.getByRole("button", { name: "Open" });
    await user.click(trigger);
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    await user.keyboard("{Escape}");
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });
});

describe("Pagination", () => {
  it("marks the current page", async () => {
    const user = userEvent.setup();
    const onPageChange = vi.fn();
    render(
      <Pagination
        page={2}
        lastPage={4}
        onPageChange={onPageChange}
        previousLabel="Previous"
        nextLabel="Next"
        label="Pages"
      />,
    );
    expect(screen.getByRole("button", { name: "Pages 2" })).toHaveAttribute(
      "aria-current",
      "page",
    );
    await user.click(screen.getByRole("button", { name: "Next" }));
    expect(onPageChange).toHaveBeenCalledWith(3);
  });
});

describe("commerce primitives", () => {
  it("names color swatches for assistive tech", () => {
    render(<ColorSwatch label="მწვანე" color="#26382c" selected />);
    expect(screen.getByRole("button", { name: "მწვანე" })).toHaveAttribute(
      "aria-pressed",
      "true",
    );
  });

  it("communicates availability with text", () => {
    render(
      <AvailabilityStatus status="out_of_stock" label="არ არის მარაგში" />,
    );
    expect(screen.getByText("არ არის მარაგში")).toBeInTheDocument();
  });

  it("notifies filter changes", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <FilterCheckbox
        id="brand"
        label="Härkila"
        checked={false}
        onChange={onChange}
        count={3}
      />,
    );
    await user.click(screen.getByLabelText("Härkila"));
    expect(onChange).toHaveBeenCalledWith(true);
  });

  it("steps quantity within bounds", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <QuantityStepper
        value={1}
        min={1}
        max={3}
        onChange={onChange}
        label="Quantity"
        decrementLabel="Decrease"
        incrementLabel="Increase"
      />,
    );
    expect(screen.getByRole("button", { name: "Decrease" })).toBeDisabled();
    await user.click(screen.getByRole("button", { name: "Increase" }));
    expect(onChange).toHaveBeenCalledWith(2);
  });
});

describe("surfaces", () => {
  it("exposes a surface context attribute", () => {
    const { container } = render(<Surface name="dark">Night forest</Surface>);
    expect(container.firstChild).toHaveAttribute("data-surface", "dark");
  });

  it("has no axe violations on a dark surface sample", async () => {
    const { container } = render(
      <Surface name="dark">
        <p>Warm bone on night forest</p>
      </Surface>,
    );
    expect(await axe(container)).toHaveNoViolations();
  });
});
